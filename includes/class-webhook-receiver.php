<?php
namespace WPSyndication\Subscriber;

class Webhook_Receiver {

	private HMAC_Verifier $hmac;
	private JWT_Verifier $jwt;
	private Post_Importer $importer;

	public function __construct() {
		$this->hmac     = new HMAC_Verifier();
		$this->jwt      = new JWT_Verifier();
		$this->importer = new Post_Importer();
	}

	/**
	 * Handle an incoming webhook request.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$secret_key = get_option( 'wpss_secret_key', '' );

		if ( empty( $secret_key ) ) {
			return new \WP_REST_Response( array( 'error' => 'Subscriber not configured.' ), 503 );
		}

		// 1. Verify JWT in Authorization header.
		$auth_header = $request->get_header( 'authorization' );
		$token       = $this->extract_bearer_token( $auth_header );

		if ( ! $token || ! $this->jwt->verify( $token, $secret_key ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid token.' ), 401 );
		}

		// 2. Verify HMAC signature of the full body.
		if ( ! $this->hmac->verify( $request, $secret_key ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid signature.' ), 401 );
		}

		$payload = $request->get_json_params();

		if ( empty( $payload['event'] ) || empty( $payload['post_id'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid payload.' ), 400 );
		}

		// 3. Idempotency check — skip duplicate deliveries.
		if ( ! empty( $payload['idempotency_key'] ) ) {
			if ( $this->already_processed( $payload['idempotency_key'] ) ) {
				return new \WP_REST_Response( array( 'status' => 'already_processed' ), 200 );
			}
		}

		// 4. Process the event.
		$result = $this->process_event( $payload );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 422 );
		}

		// 5. Mark as processed (idempotency).
		if ( ! empty( $payload['idempotency_key'] ) ) {
			$this->mark_processed( $payload['idempotency_key'] );
		}

		return new \WP_REST_Response( array( 'status' => 'ok', 'local_post_id' => $result ), 200 );
	}

	private function process_event( array $payload ) {
		switch ( $payload['event'] ) {
			case 'publish':
			case 'update':
				return $this->importer->import( $payload );

			case 'delete':
				$this->importer->handle_delete( $payload );
				return 0;

			default:
				return new \WP_Error( 'unknown_event', 'Unknown event type: ' . $payload['event'] );
		}
	}

	private function extract_bearer_token( ?string $header ): ?string {
		if ( ! $header || 0 !== strpos( $header, 'Bearer ' ) ) {
			return null;
		}
		return substr( $header, 7 );
	}

	private function already_processed( string $key ): bool {
		global $wpdb;
		$table = $wpdb->prefix . WPSS_TABLE_IDEMPOTENCY;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key = %s LIMIT 1", $key )
		);
	}

	private function mark_processed( string $key ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . WPSS_TABLE_IDEMPOTENCY,
			array( 'idempotency_key' => $key ),
			array( '%s' )
		);
	}
}
