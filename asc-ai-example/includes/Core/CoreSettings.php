<?php
/**
 * Core settings for the example site.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Core\Media;

/**
 * Shared settings for admin and front rendering.
 */
class CoreSettings {

	/**
	 * Option key for plugin settings.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'example_site_settings';

	/**
	 * Example Settings admin menu slug (Boiler Settings hub; slug unchanged for compatibility).
	 *
	 * @var string
	 */
	public const ADMIN_SETTINGS_PAGE_SLUG = 'example-settings';

	/**
	 * Image setting keys (attachment IDs).
	 */
	public const SETTING_IMAGE_BLOG_DEFAULT = 'image_blog_default_id';
	public const SETTING_IMAGE_SERVICES = 'image_services_default_id';
	public const SETTING_IMAGE_PROJECTS = 'image_projects_default_id';

	public const CONTENT_TYPE_SERVICES = 'services';
	public const CONTENT_TYPE_PROJECTS = 'projects';

	/**
	 * Home Contact / Request Quote CTA band partial under content/partials/.
	 *
	 * @var string
	 */
	public const CONTACT_CALL_TO_ACTION_PARTIAL_FILE = 'contact-call-to-action.html';

	/**
	 * Agency boiler sync filename under content/partials/.
	 *
	 * @var string
	 */
	public const AGENCY_BOILERPLATE_PARTIAL_FILE = 'agency-boiler.html';

	/**
	 * Blog boiler sync filename under content/partials/.
	 *
	 * @var string
	 */
	public const BLOG_BOILERPLATE_PARTIAL_FILE = 'blog-boiler.html';

	/**
	 * Social links sync filename under content/partials/.
	 *
	 * @var string
	 */
	public const SOCIAL_LINKS_PARTIAL_FILE = 'social-links.html';

	/**
	 * Get all image setting keys.
	 *
	 * @return array<string>
	 */
	public static function get_image_setting_keys(): array {
		return array(
			self::SETTING_IMAGE_BLOG_DEFAULT,
			self::SETTING_IMAGE_SERVICES,
			self::SETTING_IMAGE_PROJECTS,
		);
	}

	/**
	 * Get image-setting defaults.
	 *
	 * @return array<string, int>
	 */
	public static function get_default_settings(): array {
		return array(
			self::SETTING_IMAGE_BLOG_DEFAULT => 0,
			self::SETTING_IMAGE_SERVICES => 0,
			self::SETTING_IMAGE_PROJECTS => 0,
		);
	}

	/**
	 * Get plugin settings merged with defaults.
	 *
	 * @return array<string, int>
	 */
	public static function get_settings(): array {
		$defaults = self::get_default_settings();
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = $defaults;
		foreach ( self::get_image_setting_keys() as $key ) {
			if ( isset( $stored[ $key ] ) ) {
				$settings[ $key ] = (int) $stored[ $key ];
			}
		}

		if ( 0 === $settings[ self::SETTING_IMAGE_SERVICES ] ) {
			$legacy = max(
				(int) ( $stored['image_services_environmental_id'] ?? 0 ),
				(int) ( $stored['image_services_industrial_id'] ?? 0 )
			);
			if ( $legacy > 0 ) {
				$settings[ self::SETTING_IMAGE_SERVICES ] = $legacy;
			}
		}

		if ( 0 === $settings[ self::SETTING_IMAGE_PROJECTS ] ) {
			$legacy = max(
				(int) ( $stored['image_projects_environmental_id'] ?? 0 ),
				(int) ( $stored['image_projects_industrial_id'] ?? 0 )
			);
			if ( $legacy > 0 ) {
				$settings[ self::SETTING_IMAGE_PROJECTS ] = $legacy;
			}
		}

		return $settings;
	}

	/**
	 * Sanitize posted image settings.
	 *
	 * @param mixed $input Posted value.
	 *
	 * @return array<string, int>
	 */
	public static function sanitize_image_settings_input( mixed $input ): array {
		$sanitized = self::get_default_settings();
		if ( ! is_array( $input ) ) {
			return $sanitized;
		}

		foreach ( self::get_image_setting_keys() as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}
			$value = absint( $input[ $key ] );
			if ( $value > 0 ) {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Get configured attachment ID for image key.
	 *
	 * @param string $key Setting key.
	 *
	 * @return int
	 */
	public static function get_image_attachment_id( string $key ): int {
		$settings = self::get_settings();
		if ( ! isset( $settings[ $key ] ) ) {
			return 0;
		}

		return (int) $settings[ $key ];
	}

	/**
	 * Get fallback plugin media path by image key (under content/media/).
	 *
	 * @param string $key Setting key.
	 *
	 * @return string Relative path under content/media/, or empty string.
	 */
	public static function get_fallback_media_path( string $key ): string {
		$path = apply_filters( Media::FILTER_SETTING_MEDIA_PATH, '', $key );
		if ( ! is_string( $path ) ) {
			return '';
		}

		return trim( $path );
	}

	/**
	 * Get effective image URL for a key.
	 *
	 * @param string $key Setting key.
	 *
	 * @return string
	 */
	public static function get_image_url( string $key ): string {
		$attachment_id = self::get_image_attachment_id( $key );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return esc_url( $url );
			}
		}

		$fallback_path = self::get_fallback_media_path( $key );
		if ( '' !== $fallback_path ) {
			return Media::get_attachment_url_for_path( $fallback_path );
		}

		return '';
	}

	/**
	 * Get image alt text for a key.
	 *
	 * @param string $key Setting key.
	 * @param string $fallback_alt Fallback alt.
	 *
	 * @return string
	 */
	public static function get_image_alt( string $key, string $fallback_alt = '' ): string {
		$attachment_id = self::get_image_attachment_id( $key );
		if ( $attachment_id <= 0 ) {
			return $fallback_alt;
		}

		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt ) {
			return $alt;
		}

		$title = get_the_title( $attachment_id );
		if ( is_string( $title ) && '' !== trim( $title ) ) {
			return $title;
		}

		return $fallback_alt;
	}
}
