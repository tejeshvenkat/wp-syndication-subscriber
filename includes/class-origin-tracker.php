<?php
namespace WPSyndication\Subscriber;

class Origin_Tracker {

	private const META_SOURCE_URL    = '_wpss_source_url';
	private const META_CANONICAL_URL = '_wpss_canonical_url';
	private const META_SOURCE_POST_ID = '_wpss_source_post_id';
	private const META_SYNCED_AT     = '_wpss_synced_at';

	public function save( int $local_post_id, array $payload ): void {
		update_post_meta( $local_post_id, self::META_SOURCE_URL, sanitize_url( $payload['source_url'] ) );
		update_post_meta( $local_post_id, self::META_CANONICAL_URL, sanitize_url( $payload['canonical_url'] ?? '' ) );
		update_post_meta( $local_post_id, self::META_SOURCE_POST_ID, (int) $payload['post_id'] );
		update_post_meta( $local_post_id, self::META_SYNCED_AT, current_time( 'mysql', true ) );
	}

	/**
	 * Find a local post by its source URL and remote post ID.
	 * Used to map updates/deletes to the correct local post.
	 *
	 * @param string $source_url
	 * @param int    $source_post_id
	 * @return int|null Local post ID or null if not found.
	 */
	public function find_local_post( string $source_url, int $source_post_id ): ?int {
		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = %s AND meta_value = %d
				LIMIT 1",
				self::META_SOURCE_POST_ID,
				$source_post_id
			)
		);

		if ( ! $post_id ) {
			return null;
		}

		$stored_source = get_post_meta( (int) $post_id, self::META_SOURCE_URL, true );
		if ( trailingslashit( $stored_source ) !== trailingslashit( $source_url ) ) {
			return null;
		}

		return (int) $post_id;
	}

	public function get_source_info( int $local_post_id ): array {
		return array(
			'source_url'     => get_post_meta( $local_post_id, self::META_SOURCE_URL, true ),
			'canonical_url'  => get_post_meta( $local_post_id, self::META_CANONICAL_URL, true ),
			'source_post_id' => get_post_meta( $local_post_id, self::META_SOURCE_POST_ID, true ),
			'synced_at'      => get_post_meta( $local_post_id, self::META_SYNCED_AT, true ),
		);
	}
}
