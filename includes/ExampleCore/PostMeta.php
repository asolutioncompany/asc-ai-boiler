<?php
/**
 * Post meta keys and query fragments shared by the front end and admin save handlers.
 *
 * Not UI: front templates query these keys; editors set them in the dashboard.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleCore;

use WP_Query;

/**
 * Example site custom post meta (featured, new, tags, project gallery).
 */
class PostMeta {

	/**
	 * Featured meta key (CPTs).
	 *
	 * @var string
	 */
	public const FEATURED_META_KEY = '_example_site_featured';

	/**
	 * New-label meta key (CPTs).
	 *
	 * @var string
	 */
	public const NEW_META_KEY = '_example_site_new';

	/**
	 * Primary fixed-tag selection (services, projects, blog).
	 *
	 * @var string
	 */
	public const PRIMARY_TAG_META_KEY = '_example_site_primary_tag';

	/**
	 * Additional photos for projects (stored as a comma-separated list of attachment IDs).
	 *
	 * @var string
	 */
	public const PROJECT_GALLERY_META_KEY = '_example_site_project_gallery';

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
	 * Maximum number of additional photos (beyond the featured image) per project.
	 *
	 * @var int
	 */
	public const PROJECT_GALLERY_MAX_PHOTOS = 5;

	/**
	 * Parse a stored gallery meta value into a clamped list of attachment IDs.
	 *
	 * @param mixed $raw Stored meta value.
	 *
	 * @return list<int>
	 */
	public static function parse_project_gallery_attachment_ids( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			$raw_string = implode( ',', $raw );
		} else {
			$raw_string = (string) $raw;
		}

		if ( '' === trim( $raw_string ) ) {
			return array();
		}

		$ids = array();
		$parts = explode( ',', $raw_string );
		foreach ( $parts as $part ) {
			$id = absint( trim( $part ) );
			if ( $id <= 0 ) {
				continue;
			}
			if ( in_array( $id, $ids, true ) ) {
				continue;
			}
			$ids[] = $id;
			if ( count( $ids ) >= self::PROJECT_GALLERY_MAX_PHOTOS ) {
				break;
			}
		}

		return $ids;
	}

	/**
	 * Clear featured flag on all other posts of this type (single featured per CPT).
	 *
	 * @param string $post_type Post type key.
	 * @param int $except_post_id Post ID to keep unchanged.
	 *
	 * @return void
	 */
	public static function clear_featured_meta_on_other_posts( string $post_type, int $except_post_id ): void {
		$query = new WP_Query(
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
			update_post_meta( (int) $other_post_id, self::FEATURED_META_KEY, '0' );
		}
	}
}
