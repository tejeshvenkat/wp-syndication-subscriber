<?php
namespace WPSyndication\Subscriber;

class HMAC_Verifier {

	private const TIMESTAMP_TOLERANCE = 300; // 5 minutes

	/**
	 * Verify the HMAC signature on an incoming webhook request.
	 *
	 * @param \WP_REST_Request $request
	 * @param string           $secret_key
	 * @return bool
	 */
	public function verify( \WP_REST_Request $request, string $secret_key ): bool {
		$signature = $request->get_header( 'x_wps_signature' );
		$timestamp = $request->get_header( 'x_wps_timestamp' );

		if ( ! $signature || ! $timestamp ) {
			return false;
		}

		// Reject replayed requests older than 5 minutes.
		if ( abs( time() - (int) $timestamp ) > self::TIMESTAMP_TOLERANCE ) {
			return false;
		}

		$body           = $request->get_body();
		$signed_content = $timestamp . '.' . $body;
		$expected       = 'sha256=' . hash_hmac( 'sha256', $signed_content, $secret_key );

		// Constant-time comparison prevents timing attacks.
		return hash_equals( $expected, $signature );
	}
}
