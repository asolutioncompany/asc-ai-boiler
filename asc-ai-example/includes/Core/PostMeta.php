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
	public const PORTFOLIO_GALLERY_MAX_PHOTOS = 6;

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
}
