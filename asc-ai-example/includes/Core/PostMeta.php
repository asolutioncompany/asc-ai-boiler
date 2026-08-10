<?php
/**
 * Post meta keys shared by the front end and admin save handlers.
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.0
 * Custom post meta keys and helpers.
 */
class PostMeta {

	/**
	 * Featured post meta key.
	 *
	 * @var string
	 */
	public const FEATURED_META_KEY = '_example_featured';

	/**
	 * Optional blog single-post CTA button label.
	 *
	 * @var string
	 */
	public const BLOG_CTA_LINK_LABEL_META_KEY = '_example_blog_cta_link_label';

	/**
	 * Optional blog single-post CTA destination URL.
	 *
	 * @var string
	 */
	public const BLOG_CTA_LINK_URL_META_KEY = '_example_blog_cta_link_url';

	/**
	 * Portfolio post gallery attachment IDs (comma-separated).
	 *
	 * @var string
	 */
	public const PORTFOLIO_GALLERY_META_KEY = '_example_portfolio_gallery';

	/**
	 * Maximum number of photos allowed in the portfolio gallery meta box.
	 */
	public const PORTFOLIO_GALLERY_MAX_PHOTOS = 5;

	/**
	 * Clear featured flag on all other posts of this type (single featured per post type).
	 *
	 * @param string $post_type Post type key.
	 * @param int $except_post_id Post ID to keep unchanged.
	 *
	 * @return void
	 */
	public static function clear_featured_meta_on_other_posts( string $post_type, int $except_post_id ): void {
		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => -1,
				'post__not_in'           => array( $except_post_id ),
				'fields'                 => 'ids',
				'meta_key'               => self::FEATURED_META_KEY,
				'meta_value'             => '1',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $other_post_id ) {
			delete_post_meta( (int) $other_post_id, self::FEATURED_META_KEY );
		}
	}

	/**
	 * Parse comma-separated attachment IDs or an array of values into a list of positive integers.
	 *
	 * @param mixed $raw Stored meta value or raw input.
	 *
	 * @return list<int>
	 */
	public static function parse_portfolio_gallery_attachment_ids( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$parts = explode( ',', $raw );
		$ids = array();

		foreach ( $parts as $part ) {
			$id = absint( trim( $part ) );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		if ( count( $ids ) > self::PORTFOLIO_GALLERY_MAX_PHOTOS ) {
			$ids = array_slice( $ids, 0, self::PORTFOLIO_GALLERY_MAX_PHOTOS );
		}

		return array_values( $ids );
	}

	/**
	 * Sort an array of WP_Post objects so the featured post appears first, then newest by date.
	 *
	 * @param array<\WP_Post> $posts Array of posts passed by reference.
	 * @return void
	 */
	public static function sort_posts_featured_first_then_newest( array &$posts ): void {
		usort(
			$posts,
			static function ( $a, $b ): int {
				if ( ! $a instanceof \WP_Post || ! $b instanceof \WP_Post ) {
					return 0;
				}
				$fa = '1' === (string) get_post_meta( (int) $a->ID, self::FEATURED_META_KEY, true );
				$fb = '1' === (string) get_post_meta( (int) $b->ID, self::FEATURED_META_KEY, true );
				if ( $fa !== $fb ) {
					return $fa ? -1 : 1;
				}

				return strtotime( (string) $b->post_date ) <=> strtotime( (string) $a->post_date );
			}
		);
	}
}
