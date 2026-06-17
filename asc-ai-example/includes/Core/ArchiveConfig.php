<?php
/**
 * Example archive limits and slug constants.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

class ArchiveConfig {
	// Modify to test paging
	public const HOME_BLOG_LIMIT      = 6;
	public const BLOG_ARCHIVE_LIMIT   = 12;
	public const SEARCH_ARCHIVE_LIMIT = 12;

	public const SLUG_BLOG     = 'blog';
	public const SLUG_PROJECTS = 'featured-work';
	public const SLUG_SERVICES = 'our-services';

	public static function url_for_page_slug( string $slug ): string {
		$slug = trim( $slug, '/' );

		return trailingslashit( home_url( '/' . $slug . '/' ) );
	}
}
