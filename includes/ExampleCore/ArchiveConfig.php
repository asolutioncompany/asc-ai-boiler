<?php
/**
 * Example archive limits, slugs, and test-mode helpers for grid and archive shortcodes.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleCore;

/**
 * Example archive configuration (no shortcode parameters; toggles via constants).
 */
class ArchiveConfig {

	public const HOME_BLOG_LIMIT = 6;
	public const BLOG_SECTION_LIMIT = 6;
	public const PROJECT_SECTION_LIMIT = 6;
	public const ARCHIVE_PER_PAGE = 24;

	public const SLUG_BLOG = 'blog';
	public const SLUG_PROJECTS = 'featured-work';
	public const SLUG_SERVICES = 'our-services';

	public static function teaser_display_limit( int $production_limit ): int {
		if ( defined( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL' ) && ASC_AI_EXAMPLE_TEST_VIEW_ALL
			&& defined( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL_NUM' ) ) {
			return max( 1, (int) ASC_AI_EXAMPLE_TEST_VIEW_ALL_NUM );
		}

		return $production_limit;
	}

	public static function archive_per_page(): int {
		if ( defined( 'ASC_AI_EXAMPLE_TEST_PAGING' ) && ASC_AI_EXAMPLE_TEST_PAGING
			&& defined( 'ASC_AI_EXAMPLE_TEST_PAGING_POST_NUM' ) ) {
			return max( 1, (int) ASC_AI_EXAMPLE_TEST_PAGING_POST_NUM );
		}

		return self::ARCHIVE_PER_PAGE;
	}

	public static function url_for_page_slug( string $slug ): string {
		$slug = trim( $slug, '/' );

		return trailingslashit( home_url( '/' . $slug . '/' ) );
	}
}
