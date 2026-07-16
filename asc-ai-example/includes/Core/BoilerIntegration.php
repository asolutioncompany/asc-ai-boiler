<?php
/**
 * Registers Example-layer hooks into boiler Admin and Core (no boiler imports of Example classes).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Admin\ContentSync;
use ASC\AI_BOILER\Core\Media;

/**
 * Example product integration with aS.c Boiler core APIs.
 */
final class BoilerIntegration {

	/**
	 * Wire Example sync profile, content types, and admin menu placement into boiler hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		ContentSyncProfile::register();
		MediaBindings::register();
		ThemeShell::register();
		add_filter( ContentSync::FILTER_SYNC_CONTENT_TYPE_KEYS, array( self::class, 'append_sync_content_type_keys' ), 10, 1 );
		add_filter( ContentSync::FILTER_CONTENT_DIR, array( self::class, 'filter_content_dir' ) );
		add_filter( ContentSync::FILTER_CONTENT_URL, array( self::class, 'filter_content_url' ) );
		add_filter( Media::FILTER_MEDIA_DIR, array( self::class, 'filter_media_dir' ) );
		add_filter( Media::FILTER_MEDIA_URL, array( self::class, 'filter_media_url' ) );
		add_filter( Media::FILTER_OTHER_MEDIA_DIR, array( self::class, 'filter_other_media_dir' ) );
		add_filter( Media::FILTER_OTHER_MEDIA_URL, array( self::class, 'filter_other_media_url' ) );
	}

	public static function filter_content_dir( string $default ): string {
		return plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/';
	}

	public static function filter_content_url( string $default ): string {
		return plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/';
	}

	public static function filter_media_dir( string $default ): string {
		return plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/media/';
	}

	public static function filter_media_url( string $default ): string {
		return plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/media/';
	}

	public static function filter_other_media_dir( string $default ): string {
		return plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/other-media/';
	}

	public static function filter_other_media_url( string $default ): string {
		return plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/other-media/';
	}

	/**
	 * @param list<string> $keys Built-in content sync type keys from boiler.
	 *
	 * @return list<string>
	 */
	public static function append_sync_content_type_keys( array $keys ): array {
		return array_merge(
			$keys,
			array(
				CoreSettings::CONTENT_TYPE_SERVICES,
				CoreSettings::CONTENT_TYPE_PROJECTS,
			)
		);
	}

}
