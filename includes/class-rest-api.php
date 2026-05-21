<?php
namespace WPSyndication\Subscriber;

class Rest_Api {

	private const NAMESPACE = 'wpss/v1';

	private Webhook_Receiver $receiver;

	public function __construct() {
		$this->receiver = new Webhook_Receiver();
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/receive', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this->receiver, 'handle' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/setup', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'setup' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'secret_key' => array( 'required' => true, 'type' => 'string' ),
				'jwt_token'  => array( 'required' => true, 'type' => 'string' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/status', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'status' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );
	}

	public function setup( \WP_REST_Request $request ): \WP_REST_Response {
		update_option( 'wpss_secret_key', sanitize_text_field( $request->get_param( 'secret_key' ) ) );
		update_option( 'wpss_jwt_token', sanitize_text_field( $request->get_param( 'jwt_token' ) ) );
		return rest_ensure_response( array( 'configured' => true ) );
	}

	public function status( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( array(
			'configured' => ! empty( get_option( 'wpss_secret_key' ) ),
			'version'    => WPSS_VERSION,
			'site_url'   => get_site_url(),
		) );
	}

	public function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
