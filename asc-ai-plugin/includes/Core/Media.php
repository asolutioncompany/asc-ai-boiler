<?php
/**
 * Shared Core Media helper: constants, directory paths, and URL resolution.
 *
 * Safe for both public front-end and dashboard admin routines.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core Media Helper Class.
 */
final class Media {

	/**
	 * Relative directory under the plugin root (trailing slash).
	 */
	public const MEDIA_RELATIVE_DIR = 'content/media/';

	/**
	 * Relative directory for static assets served directly (SVGs, fonts, icons) — never imported into the WP media library.
	 */
	public const MEDIA_OTHER_RELATIVE_DIR = 'content/other-media/';

	/**
	 * Attachment meta: plugin-relative path under {@see MEDIA_RELATIVE_DIR} (e.g. stock/blog-default.jpg).
	 */
	public const META_MEDIA_PATH = '_asc_ai_boiler_media_path';

	/**
	 * Filter: override the absolute path to content/media/ (trailing slash).
	 */
	public const FILTER_MEDIA_DIR = 'asc_ai_boiler_media_dir';

	/**
	 * Filter: override the public base URL of content/media/ (trailing slash).
	 */
	public const FILTER_MEDIA_URL = 'asc_ai_boiler_media_url';

	/**
	 * Filter: override the absolute path to content/other-media/ (trailing slash).
	 */
	public const FILTER_OTHER_MEDIA_DIR = 'asc_ai_boiler_other_media_dir';

	/**
	 * Filter: override the public base URL of content/other-media/ (trailing slash).
	 */
	public const FILTER_OTHER_MEDIA_URL = 'asc_ai_boiler_other_media_url';

	/**
	 * Filter: manifest media binding rows used on import and export.
	 */
	public const FILTER_MEDIA_BINDINGS = 'asc_ai_boiler_media_bindings';

	/**
	 * Filter: plugin media path for a settings image key when no attachment is configured yet.
	 */
	public const FILTER_SETTING_MEDIA_PATH = 'asc_ai_boiler_setting_media_path';

	/**
	 * Filter: plugin media path for a post (post type + slug) when no featured image is set.
	 */
	public const FILTER_POST_MEDIA_PATH = 'asc_ai_boiler_post_media_path';

	/**
	 * @return string Absolute path to content/media/ (trailing slash). Filtered by {@see FILTER_MEDIA_DIR}.
	 */
	public static function get_media_directory(): string {
		$paths = \ASC\AI_BOILER\Admin\SyncConfig::get_companion_paths();
		$default = ( $paths && isset( $paths['media_dir'] ) ) ? $paths['media_dir'] : Core::get_instance()->get_plugin_path() . self::MEDIA_RELATIVE_DIR;
		return trailingslashit( (string) apply_filters( self::FILTER_MEDIA_DIR, $default ) );
	}

	/**
	 * Absolute path to content/other-media/ (trailing slash). Filtered by {@see FILTER_OTHER_MEDIA_DIR}.
	 *
	 * @return string
	 */
	public static function get_other_media_directory(): string {
		$paths = \ASC\AI_BOILER\Admin\SyncConfig::get_companion_paths();
		$default = ( $paths && isset( $paths['other_media_dir'] ) ) ? $paths['other_media_dir'] : Core::get_instance()->get_plugin_path() . self::MEDIA_OTHER_RELATIVE_DIR;
		return trailingslashit( (string) apply_filters( self::FILTER_OTHER_MEDIA_DIR, $default ) );
	}

	/**
	 * Resolve a relative media path to a public URL, preferring the WordPress media library.
	 *
	 * @param string $relative_path Path under content/media/ (e.g. hero.jpg).
	 * @return string Public URL.
	 */
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

	/**
	 * @param string $relative_path Path under content/media/ (e.g. hero.jpg).
	 * @return string Public URL (direct plugin file, no media library lookup).
	 */
	public static function get_media_url( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}

		$paths = \ASC\AI_BOILER\Admin\SyncConfig::get_companion_paths();
		$default = ( $paths && isset( $paths['media_url'] ) ) ? $paths['media_url'] : Core::get_instance()->get_plugin_url() . self::MEDIA_RELATIVE_DIR;
		$base = trailingslashit( (string) apply_filters( self::FILTER_MEDIA_URL, $default ) );

		return esc_url( $base . $relative_path );
	}

	/**
	 * Public URL for a file under content/other-media/. Files are served directly — not imported into WordPress.
	 *
	 * @param string $relative_path Path relative to content/other-media/ (e.g. `moon.svg`).
	 * @return string Escaped public URL.
	 */
	public static function get_other_media_url( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}
		$paths = \ASC\AI_BOILER\Admin\SyncConfig::get_companion_paths();
		$default = ( $paths && isset( $paths['other_media_url'] ) ) ? $paths['other_media_url'] : Core::get_instance()->get_plugin_url() . self::MEDIA_OTHER_RELATIVE_DIR;
		$base = trailingslashit( (string) apply_filters( self::FILTER_OTHER_MEDIA_URL, $default ) );
		return esc_url( $base . $relative_path );
	}

	/**
	 * @param string $post_type Post type slug.
	 * @param string $slug Post slug.
	 * @return string Plugin media URL or empty string.
	 */
	public static function get_post_media_url( string $post_type, string $slug ): string {
		$relative_path = apply_filters( self::FILTER_POST_MEDIA_PATH, '', $post_type, $slug );
		if ( ! is_string( $relative_path ) || '' === $relative_path ) {
			return '';
		}

		return self::get_attachment_url_for_path( $relative_path );
	}

	/**
	 * @param string $relative_path Media path key.
	 * @return int Attachment ID.
	 */
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

	/**
	 * Normalize path helper.
	 */
	public static function normalize_relative_path( string $path ): string {
		$path = trim( wp_normalize_path( $path ) );
		$path = ltrim( $path, '/' );
		return $path;
	}

	/**
	 * @return string Absolute path to content-manifest.json.
	 */
	public static function get_content_manifest_path(): string {
		return dirname( rtrim( self::get_media_directory(), '/' ) ) . '/content-manifest.json';
	}
}
