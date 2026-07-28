<?php
/**
 * Core settings for the minimum example site.
 *
 * @package asc-ai-example
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\Media;

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
	 * Settings admin menu slug (Boiler Settings hub).
	 *
	 * @var string
	 */
	public const ADMIN_SETTINGS_PAGE_SLUG = 'example-settings';

	/**
	 * Image setting keys (attachment IDs).
	 */
	public const SETTING_IMAGE_BLOG_DEFAULT = 'image_blog_default_id';
	public const SETTING_IMAGE_PORTFOLIO = 'image_portfolio_default_id';

	public const CONTENT_TYPE_PORTFOLIO = 'portfolio';

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
			self::SETTING_IMAGE_PORTFOLIO,
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
			self::SETTING_IMAGE_PORTFOLIO => 0,
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
		$out = self::get_default_settings();
		if ( ! is_array( $input ) ) {
			return $out;
		}

		foreach ( self::get_image_setting_keys() as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$val = absint( $input[ $key ] );
				if ( $val > 0 ) {
					$out[ $key ] = $val;
				}
			}
		}

		return $out;
	}

	/**
	 * Public URL for a configuration image key, fall back to plugin content/media/.
	 *
	 * @param string $key Settings key.
	 *
	 * @return string Image absolute URL or empty string.
	 */
	public static function get_image_url( string $key ): string {
		$settings = self::get_settings();
		$attachment_id = $settings[ $key ] ?? 0;

		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return esc_url( $url );
			}
		}

		return Media::get_attachment_url_for_path( 'blog-default.jpg' );
	}

	/**
	 * Image alt description for a configuration key.
	 *
	 * @param string $key Settings key.
	 * @param string $fallback Alternative text fallback.
	 *
	 * @return string Alt attribute text description.
	 */
	public static function get_image_alt( string $key, string $fallback ): string {
		$settings = self::get_settings();
		$attachment_id = $settings[ $key ] ?? 0;

		if ( $attachment_id > 0 ) {
			$alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( '' !== trim( $alt ) ) {
				return $alt;
			}
		}

		return $fallback;
	}
}
