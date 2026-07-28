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

	private static function bool_to_option_val( bool $enabled ): string {
		if ( $enabled ) {
			return '1';
		}
		return '0';
	}

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
	 * Option key to store the companion plugin slug.
	 *
	 * @var string
	 */
	public const OPTION_COMPANION_SLUG = 'asc_ai_boiler_companion_slug';

	/**
	 * Transient key to cache resolved companion paths.
	 *
	 * @var string
	 */
	public const TRANSIENT_COMPANION_PATHS = 'asc_ai_boiler_companion_paths';

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
			self::bool_to_option_val( $enabled )
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
			self::bool_to_option_val( $enabled )
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
			self::bool_to_option_val( $enabled )
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
			self::bool_to_option_val( $enabled )
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
		update_option( self::OPTION_SYNC_PAGES, self::bool_to_option_val( $enabled ) );
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
		update_option( self::OPTION_SYNC_PARTIALS, self::bool_to_option_val( $enabled ) );
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
		update_option( self::OPTION_SYNC_POSTS, self::bool_to_option_val( $enabled ) );
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
		update_option( self::OPTION_SYNC_CUSTOM_POST_TYPES, self::bool_to_option_val( $enabled ) );
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
		update_option( self::OPTION_SYNC_MEDIA, self::bool_to_option_val( $enabled ) );
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
		update_option( self::OPTION_ENABLE_SYNC_PAGE, self::bool_to_option_val( $enabled ) );
	}

	/**
	 * Get companion plugin slug.
	 *
	 * @return string
	 */
	public static function get_companion_slug(): string {
		return trim( (string) get_option( self::OPTION_COMPANION_SLUG, '' ) );
	}

	/**
	 * Set companion plugin slug. Clears paths transient on change.
	 *
	 * @param string $slug Slug name.
	 * @return void
	 */
	public static function set_companion_slug( string $slug ): void {
		$old_slug = self::get_companion_slug();
		$new_slug = trim( $slug );
		if ( $old_slug !== $new_slug ) {
			update_option( self::OPTION_COMPANION_SLUG, $new_slug );
			delete_transient( self::TRANSIENT_COMPANION_PATHS );
		}
	}

	/**
	 * Get dynamic companion plugin paths, caching in transient for performance.
	 *
	 * @return array{companion_slug:string, content_dir:string, content_url:string, media_dir:string, media_url:string, other_media_dir:string, other_media_url:string, is_active:bool}|null
	 */
	public static function get_companion_paths(): ?array {
		$paths = get_transient( self::TRANSIENT_COMPANION_PATHS );
		if ( is_array( $paths ) ) {
			return $paths;
		}

		$slug = self::get_companion_slug();
		if ( '' === $slug || 1 !== preg_match( '/^[a-z0-9\-]+$/', $slug ) ) {
			return null;
		}

		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		if ( ! is_dir( $plugin_dir ) ) {
			return null;
		}

		// Fast active check by inspecting active_plugins option
		$active_plugins = get_option( 'active_plugins', array() );
		$is_active = false;
		if ( is_array( $active_plugins ) ) {
			foreach ( $active_plugins as $plugin_file ) {
				if ( 0 === strpos( $plugin_file, $slug . '/' ) ) {
					$is_active = true;
					break;
				}
			}
		}

		$paths = array(
			'companion_slug' => $slug,
			'content_dir' => trailingslashit( $plugin_dir . '/' . self::CONTENT_RELATIVE_ROOT ),
			'content_url' => trailingslashit( plugins_url( $slug ) . '/' . self::CONTENT_RELATIVE_ROOT ),
			'media_dir' => trailingslashit( $plugin_dir . '/' . self::CONTENT_RELATIVE_ROOT . 'media' ),
			'media_url' => trailingslashit( plugins_url( $slug ) . '/' . self::CONTENT_RELATIVE_ROOT . 'media' ),
			'other_media_dir' => trailingslashit( $plugin_dir . '/' . self::CONTENT_RELATIVE_ROOT . 'other-media' ),
			'other_media_url' => trailingslashit( plugins_url( $slug ) . '/' . self::CONTENT_RELATIVE_ROOT . 'other-media' ),
			'is_active' => $is_active,
		);

		set_transient( self::TRANSIENT_COMPANION_PATHS, $paths, DAY_IN_SECONDS );

		return $paths;
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
		delete_option( self::OPTION_COMPANION_SLUG );
		delete_transient( self::TRANSIENT_COMPANION_PATHS );
	}

	/**
	 * @param string $option_key Option name.
	 * @param bool $default Whether the option should be enabled by default.
	 *
	 * @return bool
	 */
	private static function is_enabled( string $option_key, bool $default ): bool {
		$fallback = '0';
		if ( $default ) {
			$fallback = '1';
		}
		return '1' === (string) get_option( $option_key, $fallback );
	}
}
