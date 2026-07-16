<?php
/**
 * Boiler static sync (export / import): batch size and persisted sync options.
 *
 * Option keys use the `asc_ai_boiler_` prefix.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync configuration for {@see ContentSync} and the Import / Export admin UI.
 */
final class SyncConfig {

	/**
	 * Relative path prefix for synced HTML under the plugin (e.g. `content/pages/home.html`).
	 *
	 * @var string
	 */
	public const CONTENT_RELATIVE_ROOT = 'content/';

	public const CONTENT_TYPE_PARTIALS = 'partials';

	public const CONTENT_TYPE_PAGES = 'pages';

	public const CONTENT_TYPE_POSTS = 'posts';

	public const CONTENT_DIR_EXCERPTS = 'excerpts';

	public const CONTENT_DIR_META_DESCRIPTIONS = 'meta-descriptions';

	public const CONTENT_DIR_SOCIAL_DESCRIPTIONS = 'social-descriptions';

	public const CONTENT_DIR_X_DESCRIPTIONS = 'x-descriptions';

	/**
	 * How many published posts (export) or plugin content files (import) to process per AJAX batch.
	 *
	 * @var int
	 */
	public const CONTENT_SYNC_BATCH_SIZE = 100;

	/**
	 * When true ("1"), export removes plugin HTML files that have no matching published content.
	 *
	 * @var string
	 */
	public const OPTION_EXPORT_CLEANUP = 'asc_ai_boiler_export_cleanup';

	/**
	 * When true ("1"), import cleanup removes published WordPress posts whose expected plugin HTML
	 * file is missing (content was removed on disk). Uses trash when the post type supports it.
	 *
	 * @var string
	 */
	public const OPTION_IMPORT_CLEANUP = 'asc_ai_boiler_import_cleanup';

	/**
	 * Developer mode for static content sync. When true ("1"), the import confirmation checkbox is pre-checked
	 * on the Import / Export screen (local/staging convenience). When false, the checkbox is off by default.
	 *
	 * @var string
	 */
	public const OPTION_DEVELOPMENT_MODE = 'asc_ai_boiler_development_mode';

	/**
	 * When true ("1"), Yoast SEO social and metadata integrations are synced.
	 *
	 * @var string
	 */
	public const OPTION_YOAST_SYNC = 'asc_ai_boiler_yoast_sync';

	/**
	 * When true ("1"), pages are synced.
	 *
	 * @var string
	 */
	public const OPTION_SYNC_PAGES = 'asc_ai_boiler_sync_pages';

	/**
	 * When true ("1"), partials are synced.
	 *
	 * @var string
	 */
	public const OPTION_SYNC_PARTIALS = 'asc_ai_boiler_sync_partials';

	/**
	 * When true ("1"), posts are synced.
	 *
	 * @var string
	 */
	public const OPTION_SYNC_POSTS = 'asc_ai_boiler_sync_posts';

	/**
	 * When true ("1"), custom post types are synced.
	 *
	 * @var string
	 */
	public const OPTION_SYNC_CUSTOM_POST_TYPES = 'asc_ai_boiler_sync_custom_post_types';

	/**
	 * When true ("1"), media attachments are synced.
	 *
	 * @var string
	 */
	public const OPTION_SYNC_MEDIA = 'asc_ai_boiler_sync_media';

	/**
	 * When true ("1"), the Import/Export screen is enabled in the menu.
	 *
	 * @var string
	 */
	public const OPTION_ENABLE_SYNC_PAGE = 'asc_ai_boiler_enable_sync_page';

	/**
	 * Export: delete plugin HTML files with no matching published WordPress content.
	 *
	 * @return bool
	 */
	public static function is_export_cleanup(): bool {
		return self::is_enabled( self::OPTION_EXPORT_CLEANUP, true );
	}

	/**
	 * @param bool $enabled Whether to delete orphan plugin files after export.
	 *
	 * @return void
	 */
	public static function set_export_cleanup( bool $enabled ): void {
		update_option(
			self::OPTION_EXPORT_CLEANUP,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Whether import cleanup is enabled (remove published synced posts whose plugin HTML file is missing).
	 *
	 * @return bool
	 */
	public static function is_import_cleanup(): bool {
		return self::is_enabled( self::OPTION_IMPORT_CLEANUP, false );
	}

	/**
	 * @param bool $enabled Whether import runs WordPress cleanup for missing plugin files.
	 *
	 * @return void
	 */
	public static function set_import_cleanup( bool $enabled ): void {
		update_option(
			self::OPTION_IMPORT_CLEANUP,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Whether developer mode is on: pre-check the import confirmation checkbox on the sync screen.
	 *
	 * @return bool
	 */
	public static function is_development_mode(): bool {
		if ( self::is_enabled( self::OPTION_DEVELOPMENT_MODE, false ) ) {
			return true;
		}

		// Legacy option name from earlier example-site builds.
		return self::is_enabled( 'example_site_development_mode', false );
	}

	/**
	 * @param bool $enabled Whether to pre-check import confirmation.
	 *
	 * @return void
	 */
	public static function set_development_mode( bool $enabled ): void {
		update_option(
			self::OPTION_DEVELOPMENT_MODE,
			$enabled ? '1' : '0'
		);
		delete_option( 'example_site_development_mode' );
	}

	/**
	 * Whether Yoast SEO sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_yoast_sync(): bool {
		return self::is_enabled( self::OPTION_YOAST_SYNC, true );
	}

	/**
	 * @param bool $enabled Whether to enable Yoast SEO sync.
	 *
	 * @return void
	 */
	public static function set_yoast_sync( bool $enabled ): void {
		update_option(
			self::OPTION_YOAST_SYNC,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Whether pages sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_pages_sync_enabled(): bool {
		return self::is_enabled( self::OPTION_SYNC_PAGES, true );
	}

	/**
	 * @param bool $enabled Whether to enable pages sync.
	 *
	 * @return void
	 */
	public static function set_pages_sync_enabled( bool $enabled ): void {
		update_option( self::OPTION_SYNC_PAGES, $enabled ? '1' : '0' );
	}

	/**
	 * Whether partials sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_partials_sync_enabled(): bool {
		return self::is_enabled( self::OPTION_SYNC_PARTIALS, true );
	}

	/**
	 * @param bool $enabled Whether to enable partials sync.
	 *
	 * @return void
	 */
	public static function set_partials_sync_enabled( bool $enabled ): void {
		update_option( self::OPTION_SYNC_PARTIALS, $enabled ? '1' : '0' );
	}

	/**
	 * Whether posts sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_posts_sync_enabled(): bool {
		return self::is_enabled( self::OPTION_SYNC_POSTS, true );
	}

	/**
	 * @param bool $enabled Whether to enable posts sync.
	 *
	 * @return void
	 */
	public static function set_posts_sync_enabled( bool $enabled ): void {
		update_option( self::OPTION_SYNC_POSTS, $enabled ? '1' : '0' );
	}

	/**
	 * Whether custom post types sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_custom_post_types_sync_enabled(): bool {
		return self::is_enabled( self::OPTION_SYNC_CUSTOM_POST_TYPES, true );
	}

	/**
	 * @param bool $enabled Whether to enable custom post types sync.
	 *
	 * @return void
	 */
	public static function set_custom_post_types_sync_enabled( bool $enabled ): void {
		update_option( self::OPTION_SYNC_CUSTOM_POST_TYPES, $enabled ? '1' : '0' );
	}

	/**
	 * Whether media sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_media_sync_enabled(): bool {
		return self::is_enabled( self::OPTION_SYNC_MEDIA, true );
	}

	/**
	 * @param bool $enabled Whether to enable media sync.
	 *
	 * @return void
	 */
	public static function set_media_sync_enabled( bool $enabled ): void {
		update_option( self::OPTION_SYNC_MEDIA, $enabled ? '1' : '0' );
	}

	/**
	 * Whether the Import/Export page is enabled.
	 *
	 * @return bool
	 */
	public static function is_sync_page_enabled(): bool {
		return self::is_enabled( self::OPTION_ENABLE_SYNC_PAGE, true );
	}

	/**
	 * @param bool $enabled Whether to enable the Import/Export page.
	 *
	 * @return void
	 */
	public static function set_sync_page_enabled( bool $enabled ): void {
		update_option( self::OPTION_ENABLE_SYNC_PAGE, $enabled ? '1' : '0' );
	}

	/**
	 * Check if a specific content type key is enabled for sync.
	 *
	 * @param string $type_key Content type key (e.g. pages, partials, posts, etc).
	 *
	 * @return bool
	 */
	public static function is_content_type_enabled( string $type_key ): bool {
		if ( self::CONTENT_TYPE_PAGES === $type_key ) {
			return self::is_pages_sync_enabled();
		}
		if ( self::CONTENT_TYPE_PARTIALS === $type_key ) {
			return self::is_partials_sync_enabled();
		}
		if ( self::CONTENT_TYPE_POSTS === $type_key ) {
			return self::is_posts_sync_enabled();
		}
		return self::is_custom_post_types_sync_enabled();
	}

	/**
	 * Remove all sync options. For uninstall.
	 *
	 * @return void
	 */
	public static function delete_all_sync_options(): void {
		delete_option( self::OPTION_EXPORT_CLEANUP );
		delete_option( self::OPTION_IMPORT_CLEANUP );
		delete_option( self::OPTION_DEVELOPMENT_MODE );
		delete_option( self::OPTION_YOAST_SYNC );
		delete_option( self::OPTION_SYNC_PAGES );
		delete_option( self::OPTION_SYNC_PARTIALS );
		delete_option( self::OPTION_SYNC_POSTS );
		delete_option( self::OPTION_SYNC_CUSTOM_POST_TYPES );
		delete_option( self::OPTION_SYNC_MEDIA );
		delete_option( self::OPTION_ENABLE_SYNC_PAGE );
	}

	/**
	 * @param string $option_key Option name.
	 * @param bool $default Whether the option should be enabled by default.
	 *
	 * @return bool
	 */
	private static function is_enabled( string $option_key, bool $default ): bool {
		$fallback = $default ? '1' : '0';
		return '1' === (string) get_option( $option_key, $fallback );
	}
}
