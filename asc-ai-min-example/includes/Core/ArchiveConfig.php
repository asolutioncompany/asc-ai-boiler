<?php
/**
 * Minimum example archive limits and slug constants.
 *
 * @package asc-ai-min-example
 */

declare( strict_types = 1 );

namespace ASC\AI_MIN_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchiveConfig {
	public const HOME_BLOG_LIMIT      = 6;
	public const BLOG_ARCHIVE_LIMIT   = 12;
	public const SEARCH_ARCHIVE_LIMIT = 12;

	public const SLUG_BLOG     = 'blog';

	public static function url_for_page_slug( string $slug ): string {
		$slug = trim( $slug, '/' );

		return trailingslashit( home_url( '/' . $slug . '/' ) );
	}
}
