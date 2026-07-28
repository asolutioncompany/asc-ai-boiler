<?php
/**
 * Registers Minimum Example-layer hooks into boiler Admin and Core.
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
 * Minimum example product integration with aS.c Boiler core APIs.
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
		add_filter( 'asc_ai_boiler_sync_content_type_keys', array( self::class, 'append_sync_content_type_keys' ), 10, 1 );
		add_filter( 'asc_ai_boiler_content_dir', array( self::class, 'filter_content_dir' ) );
		add_filter( 'asc_ai_boiler_content_url', array( self::class, 'filter_content_url' ) );
		add_filter( 'asc_ai_boiler_media_dir', array( self::class, 'filter_media_dir' ) );
		add_filter( 'asc_ai_boiler_media_url', array( self::class, 'filter_media_url' ) );
		add_filter( 'asc_ai_boiler_other_media_dir', array( self::class, 'filter_other_media_dir' ) );
		add_filter( 'asc_ai_boiler_other_media_url', array( self::class, 'filter_other_media_url' ) );
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
				CoreSettings::CONTENT_TYPE_PORTFOLIO,
			)
		);
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
}
