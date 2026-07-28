<?php
/**
 * Shared Core Media helper for asc-ai-example: constants, directory paths, and URL resolution.
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core Media Helper Class.
 */
final class Media {

	public const MEDIA_RELATIVE_DIR = 'content/media/';
	public const MEDIA_OTHER_RELATIVE_DIR = 'content/other-media/';
	public const META_MEDIA_PATH = '_asc_ai_boiler_media_path';

	public const FILTER_MEDIA_DIR = 'asc_ai_boiler_media_dir';
	public const FILTER_MEDIA_URL = 'asc_ai_boiler_media_url';
	public const FILTER_OTHER_MEDIA_DIR = 'asc_ai_boiler_other_media_dir';
	public const FILTER_OTHER_MEDIA_URL = 'asc_ai_boiler_other_media_url';
	public const FILTER_MEDIA_BINDINGS = 'asc_ai_boiler_media_bindings';
	public const FILTER_SETTING_MEDIA_PATH = 'asc_ai_boiler_setting_media_path';
	public const FILTER_POST_MEDIA_PATH = 'asc_ai_boiler_post_media_path';

	public static function get_media_directory(): string {
		$default = plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . self::MEDIA_RELATIVE_DIR;
		return trailingslashit( (string) apply_filters( self::FILTER_MEDIA_DIR, $default ) );
	}

	public static function get_other_media_directory(): string {
		$default = plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . self::MEDIA_OTHER_RELATIVE_DIR;
		return trailingslashit( (string) apply_filters( self::FILTER_OTHER_MEDIA_DIR, $default ) );
	}

	public static function get_attachment_url_for_path( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}

		$attachment_id = self::find_attachment_id_by_media_path( $relative_path );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $url ) && '' !== $url ) {
				return esc_url( $url );
			}
		}

		return self::get_media_url( $relative_path );
	}

	public static function get_media_url( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}

		$default = plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . self::MEDIA_RELATIVE_DIR;
		$base = trailingslashit( (string) apply_filters( self::FILTER_MEDIA_URL, $default ) );

		return esc_url( $base . $relative_path );
	}

	public static function get_other_media_url( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}

		$default = plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . self::MEDIA_OTHER_RELATIVE_DIR;
		$base = trailingslashit( (string) apply_filters( self::FILTER_OTHER_MEDIA_URL, $default ) );

		return esc_url( $base . $relative_path );
	}

	public static function get_post_media_url( string $post_type, string $slug ): string {
		$relative_path = apply_filters( self::FILTER_POST_MEDIA_PATH, '', $post_type, $slug );
		if ( ! is_string( $relative_path ) || '' === $relative_path ) {
			return '';
		}

		return self::get_attachment_url_for_path( $relative_path );
	}

	public static function find_attachment_id_by_media_path( string $relative_path ): int {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return 0;
		}

		global $wpdb;
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::META_MEDIA_PATH,
				$relative_path
			)
		);

		return $post_id ? (int) $post_id : 0;
	}

	public static function normalize_relative_path( string $path ): string {
		$path = trim( wp_normalize_path( $path ) );
		$path = ltrim( $path, '/' );
		return $path;
	}

	public static function get_content_manifest_path(): string {
		return dirname( rtrim( self::get_media_directory(), '/' ) ) . '/content-manifest.json';
	}
}
