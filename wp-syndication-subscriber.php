<?php
/**
 * Plugin Name: WP Syndication Subscriber
 * Plugin URI:  https://github.com/yourname/wp-syndication
 * Description: Receive and import syndicated content from a publisher site.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0-or-later
 * Text Domain: wp-syndication-subscriber
 */

namespace WPSyndication\Subscriber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSS_VERSION', '1.0.0' );
define( 'WPSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSS_TABLE_IDEMPOTENCY', 'wpss_idempotency' );

require_once WPSS_PLUGIN_DIR . 'includes/class-database.php';
require_once WPSS_PLUGIN_DIR . 'includes/class-jwt-verifier.php';
require_once WPSS_PLUGIN_DIR . 'includes/class-hmac-verifier.php';
require_once WPSS_PLUGIN_DIR . 'includes/class-origin-tracker.php';
require_once WPSS_PLUGIN_DIR . 'includes/class-post-importer.php';
require_once WPSS_PLUGIN_DIR . 'includes/class-webhook-receiver.php';
require_once WPSS_PLUGIN_DIR . 'includes/class-rest-api.php';

register_activation_hook( __FILE__, array( Database::class, 'install' ) );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

function deactivate(): void {
	$timestamp = wp_next_scheduled( 'wpss_cleanup_idempotency' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'wpss_cleanup_idempotency' );
	}
}

function init() {
	( new Rest_Api() )->init();
	schedule_cleanup();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Schedule weekly idempotency table cleanup.
 */
function schedule_cleanup(): void {
	if ( ! wp_next_scheduled( 'wpss_cleanup_idempotency' ) ) {
		wp_schedule_event( time(), 'weekly', 'wpss_cleanup_idempotency' );
	}
}
add_action( 'wpss_cleanup_idempotency', __NAMESPACE__ . '\\cleanup_idempotency' );

/**
 * Delete idempotency entries older than 30 days.
 */
function cleanup_idempotency(): void {
	global $wpdb;
	$table = $wpdb->prefix . WPSS_TABLE_IDEMPOTENCY;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table} WHERE processed_at < %s",
			gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
		)
	);
}
