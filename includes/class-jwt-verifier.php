<?php
namespace WPSyndication\Subscriber;

class JWT_Verifier {

	/**
	 * Verify and decode a JWT token.
	 *
	 * @param string $token
	 * @param string $secret_key
	 * @return array|false Decoded payload or false.
	 */
	public function verify( string $token, string $secret_key ) {
		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) ) {
			return false;
		}

		list( $header, $payload, $signature ) = $parts;

		$expected = $this->base64url_encode(
			hash_hmac( 'sha256', "{$header}.{$payload}", $secret_key, true )
		);

		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		$decoded = json_decode( $this->base64url_decode( $payload ), true );

		if ( ! is_array( $decoded ) ) {
			return false;
		}

		// Reject expired tokens.
		if ( isset( $decoded['exp'] ) && time() > $decoded['exp'] ) {
			return false;
		}

		return $decoded;
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private function base64url_decode( string $data ): string {
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}
}
