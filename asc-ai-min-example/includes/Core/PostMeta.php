<?php
/**
 * Post meta keys shared by the front end and admin save handlers.
 *
 * @package asc-ai-min-example
 */

declare( strict_types = 1 );

namespace ASC\AI_MIN_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimum example site custom post meta.
 */
class PostMeta {

	/**
	 * Optional blog single-post CTA button label.
	 *
	 * @var string
	 */
	public const BLOG_CTA_LINK_LABEL_META_KEY = '_min_example_blog_cta_link_label';

	/**
	 * Optional blog single-post CTA destination URL.
	 *
	 * @var string
	 */
	public const BLOG_CTA_LINK_URL_META_KEY = '_min_example_blog_cta_link_url';
}
