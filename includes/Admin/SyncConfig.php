<?php
/**
 * Boiler static sync (backup / restore): batch size and persisted sync options.
 *
 * Option keys use the `asc_ai_boiler_` prefix.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

/**
 * Sync configuration for {@see ContentSync} and the Backup / Restore admin UI.
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

	/**
	 * How many published posts (backup) or plugin content files (restore) to process per AJAX batch.
	 *
	 * @var int
	 */
	public const CONTENT_SYNC_BATCH_SIZE = 100;

	/**
	 * When true ("1"), backup removes plugin HTML files that have no matching published content.
	 *
	 * @var string
	 */
	public const OPTION_BACKUP_CLEANUP = 'asc_ai_boiler_backup_cleanup';

	/**
	 * When true ("1"), restore cleanup removes published WordPress posts whose expected plugin HTML
	 * file is missing (content was removed on disk). Uses trash when the post type supports it.
	 *
	 * @var string
	 */
	public const OPTION_RESTORE_CLEANUP = 'asc_ai_boiler_restore_cleanup';

	/**
	 * Developer mode for static content sync. When true ("1"), the restore confirmation checkbox is pre-checked
	 * on the Backup / Restore screen (local/staging convenience). When false, the checkbox is off by default.
	 *
	 * @var string
	 */
	public const OPTION_DEVELOPMENT_MODE = 'asc_ai_boiler_development_mode';

	/**
	 * Backup: delete plugin HTML files with no matching published WordPress content.
	 *
	 * @return bool
	 */
	public static function is_backup_cleanup(): bool {
		return self::is_enabled( self::OPTION_BACKUP_CLEANUP, true );
	}

	/**
	 * @param bool $enabled Whether to delete orphan plugin files after backup.
	 *
	 * @return void
	 */
	public static function set_backup_cleanup( bool $enabled ): void {
		update_option(
			self::OPTION_BACKUP_CLEANUP,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Whether restore cleanup is enabled (remove published synced posts whose plugin HTML file is missing).
	 *
	 * @return bool
	 */
	public static function is_restore_cleanup(): bool {
		return self::is_enabled( self::OPTION_RESTORE_CLEANUP, false );
	}

	/**
	 * @param bool $enabled Whether restore runs WordPress cleanup for missing plugin files.
	 *
	 * @return void
	 */
	public static function set_restore_cleanup( bool $enabled ): void {
		update_option(
			self::OPTION_RESTORE_CLEANUP,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Whether developer mode is on: pre-check the restore confirmation checkbox on the sync screen.
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
	 * @param bool $enabled Whether to pre-check restore confirmation.
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
		delete_option( self::OPTION_BACKUP_CLEANUP );
		delete_option( self::OPTION_RESTORE_CLEANUP );
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
