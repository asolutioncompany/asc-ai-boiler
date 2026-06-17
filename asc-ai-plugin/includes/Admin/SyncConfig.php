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
	 * Remove all sync options. For uninstall.
	 *
	 * @return void
	 */
	public static function delete_all_sync_options(): void {
		delete_option( self::OPTION_EXPORT_CLEANUP );
		delete_option( self::OPTION_IMPORT_CLEANUP );
		delete_option( self::OPTION_DEVELOPMENT_MODE );
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
