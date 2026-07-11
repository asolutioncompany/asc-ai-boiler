<?php
/**
 * Loads partial CPT posts by logical key stored in {@see RegisterPartials::META_PARTIAL_KEY}.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;
use WP_Query;

/**
 * Store helpers for partial CPT posts (boiler).
 */
final class PartialStore {

	/**
	 * @var array<string, WP_Post|null>
	 */
	private static array $post_cache = array();

	/**
	 * @param string $key Logical partial key stored in post meta (product-defined; e.g. `header`, `footer`).
	 *
	 * @return WP_Post|null
	 */
	public static function get_post_by_partial_key( string $key, bool $any_editable_status = false ): ?WP_Post {
		$partial_key = trim( $key );
		if ( '' === $partial_key ) {
			return null;
		}

		$cache_key = $partial_key . '|' . ( $any_editable_status ? '1' : '0' );
		if ( array_key_exists( $cache_key, self::$post_cache ) ) {
			return self::$post_cache[ $cache_key ];
		}

		$post = self::query_post_by_partial_meta(
			$partial_key,
			RegisterPartials::POST_TYPE,
			RegisterPartials::META_PARTIAL_KEY,
			$any_editable_status
		);

		self::$post_cache[ $cache_key ] = $post;
		return $post;
	}

	/**
	 * Raw post_content for a partial key (not passed through do_shortcode).
	 *
	 * @param string $key Partial key.
	 *
	 * @return string
	 */
	public static function get_raw_markup( string $key ): string {
		$post = self::get_post_by_partial_key( $key );
		if ( null === $post ) {
			return '';
		}

		return (string) $post->post_content;
	}

	/**
	 * Create an empty published shell post with meta when none exists (static import / sync).
	 *
	 * @param string $partial_key Logical partial key.
	 * @param string $title Post title.
	 *
	 * @return WP_Post|null Post on success.
	 */
	public static function create_shell_post_if_missing( string $partial_key, string $title ): ?WP_Post {
		$partial_key = trim( $partial_key );
		if ( '' === $partial_key ) {
			return null;
		}

		$existing = self::get_post_by_partial_key( $partial_key, true );
		if ( null !== $existing ) {
			return $existing;
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => RegisterPartials::POST_TYPE,
				'post_status' => 'publish',
				'post_title' => $title,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		update_post_meta( $post_id, RegisterPartials::META_PARTIAL_KEY, $partial_key );

		self::$post_cache = array();

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}

	/**
	 * Clear cached partial posts (after creating or changing partial meta).
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {
		self::$post_cache = array();
	}

	/**
	 * @param string $meta_value Partial key in post meta.
	 * @param string $post_type Post type slug.
	 * @param string $meta_key Meta key storing the logical partial key.
	 *
	 * @return WP_Post|null
	 */
	private static function query_post_by_partial_meta(
		string $meta_value,
		string $post_type,
		string $meta_key,
		bool $any_editable_status
	): ?WP_Post {
		$statuses = array( 'publish' );
		if ( $any_editable_status ) {
			$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );
		}

		$query = new WP_Query(
			array(
				'post_type' => $post_type,
				'post_status' => $statuses,
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'meta_key' => $meta_key,
				'meta_value' => $meta_value,
			)
		);

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return null;
		}

		$post = $query->posts[0];
		wp_reset_postdata();

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}
}
