<?php
namespace WPSyndication\Subscriber;

class Post_Importer {

	private Origin_Tracker $tracker;

	public function __construct() {
		$this->tracker = new Origin_Tracker();
	}

	/**
	 * Import or update a post from a publish/update payload.
	 *
	 * @param array $payload
	 * @return int|\WP_Error Local post ID or error.
	 */
	public function import( array $payload ) {
		$post_data = $payload['post'] ?? array();

		if ( empty( $post_data['title'] ) || empty( $post_data['content'] ) ) {
			return new \WP_Error( 'invalid_payload', 'Missing required post data.' );
		}

		$local_post_id = $this->tracker->find_local_post( $payload['source_url'], (int) $payload['post_id'] );

		$insert_args = array(
			'post_title'   => sanitize_text_field( $post_data['title'] ),
			'post_content' => wp_kses_post( $post_data['content'] ),
			'post_excerpt' => sanitize_textarea_field( $post_data['excerpt'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => $this->resolve_post_type( $post_data['post_type'] ?? 'post' ),
			'post_name'    => sanitize_title( $post_data['slug'] ?? '' ),
			'post_date_gmt' => $post_data['date'] ?? current_time( 'mysql', true ),
		);

		if ( $local_post_id ) {
			$insert_args['ID'] = $local_post_id;
			$result            = wp_update_post( $insert_args, true );
		} else {
			$result = wp_insert_post( $insert_args, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$local_post_id = (int) $result;

		$this->tracker->save( $local_post_id, $payload );
		$this->set_terms( $local_post_id, $post_data );

		return $local_post_id;
	}

	/**
	 * Handle a delete event — mark post as "source removed" rather than deleting.
	 * The subscriber site owns its copy and decides what happens to it.
	 *
	 * @param array $payload
	 * @return bool
	 */
	public function handle_delete( array $payload ): bool {
		$local_post_id = $this->tracker->find_local_post( $payload['source_url'], (int) $payload['post_id'] );

		if ( ! $local_post_id ) {
			return false;
		}

		update_post_meta( $local_post_id, '_wpss_source_removed', '1' );
		update_post_meta( $local_post_id, '_wpss_source_removed_at', current_time( 'mysql', true ) );

		do_action( 'wpss_source_post_deleted', $local_post_id, $payload );

		return true;
	}

	private function set_terms( int $post_id, array $post_data ): void {
		if ( ! empty( $post_data['categories'] ) && is_array( $post_data['categories'] ) ) {
			$cat_ids = array_map( function ( $name ) {
				$term = get_term_by( 'name', $name, 'category' );
				if ( $term ) {
					return $term->term_id;
				}
				$new = wp_insert_term( sanitize_text_field( $name ), 'category' );
				return is_wp_error( $new ) ? null : $new['term_id'];
			}, $post_data['categories'] );

			wp_set_post_categories( $post_id, array_filter( $cat_ids ) );
		}

		if ( ! empty( $post_data['tags'] ) && is_array( $post_data['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $post_data['tags'] ) );
		}
	}

	private function resolve_post_type( string $post_type ): string {
		$allowed = apply_filters( 'wpss_allowed_post_types', array( 'post', 'page' ) );
		return in_array( $post_type, $allowed, true ) ? $post_type : 'post';
	}
}
