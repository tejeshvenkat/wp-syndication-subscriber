<?php
namespace WPSyndication\Subscriber\Tests;

use WPSyndication\Subscriber\HMAC_Verifier;
use WP_UnitTestCase;
use WP_REST_Request;

class Test_HMAC_Verifier extends WP_UnitTestCase {

	private HMAC_Verifier $verifier;

	public function setUp(): void {
		parent::setUp();
		$this->verifier = new HMAC_Verifier();
	}

	private function make_request( string $body, string $signature, string $timestamp ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wpss/v1/receive' );
		$request->set_body( $body );
		$request->set_header( 'x_wps_signature', $signature );
		$request->set_header( 'x_wps_timestamp', $timestamp );
		return $request;
	}

	public function test_valid_signature_passes(): void {
		$body      = '{"event":"publish","post_id":1}';
		$secret    = 'my-super-secret';
		$timestamp = (string) time();
		$signature = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

		$request = $this->make_request( $body, $signature, $timestamp );
		$this->assertTrue( $this->verifier->verify( $request, $secret ) );
	}

	public function test_wrong_secret_fails(): void {
		$body      = '{"event":"publish","post_id":1}';
		$timestamp = (string) time();
		$signature = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, 'correct-secret' );

		$request = $this->make_request( $body, $signature, $timestamp );
		$this->assertFalse( $this->verifier->verify( $request, 'wrong-secret' ) );
	}

	public function test_expired_timestamp_fails(): void {
		$body      = '{"event":"publish","post_id":1}';
		$secret    = 'my-super-secret';
		$timestamp = (string) ( time() - 400 ); // 400 seconds ago — over 5 min tolerance
		$signature = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

		$request = $this->make_request( $body, $signature, $timestamp );
		$this->assertFalse( $this->verifier->verify( $request, $secret ) );
	}

	public function test_missing_signature_header_fails(): void {
		$request = new WP_REST_Request( 'POST', '/wpss/v1/receive' );
		$request->set_body( '{"event":"publish"}' );
		$request->set_header( 'x_wps_timestamp', (string) time() );

		$this->assertFalse( $this->verifier->verify( $request, 'secret' ) );
	}

	public function test_tampered_body_fails(): void {
		$original  = '{"event":"publish","post_id":1}';
		$secret    = 'my-super-secret';
		$timestamp = (string) time();
		$signature = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $original, $secret );

		$tampered = '{"event":"publish","post_id":999}';
		$request  = $this->make_request( $tampered, $signature, $timestamp );
		$this->assertFalse( $this->verifier->verify( $request, $secret ) );
	}
}
