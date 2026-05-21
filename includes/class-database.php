<?php
namespace WPSyndication\Subscriber;

class Database {

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . WPSS_TABLE_IDEMPOTENCY;

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			idempotency_key VARCHAR(64) NOT NULL,
			processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idempotency_key (idempotency_key)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'wpss_db_version', WPSS_VERSION );
	}
}
