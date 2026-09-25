<?php
/**
 * Site shell: partial shortcodes and home URL helper.
 *
 * @package asc-ai-example
 * @since 1.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\Media;
use ASC\AI_EXAMPLE\Core\PartialCatalog;
use ASC\AI_EXAMPLE\Core\PartialStore;

/**
 * Site Front Class
 */
class SiteFront {

	/**
	 * Home hero image from content/media/hero.jpg.
	 *
	 * @return string
	 */
	public function render_hero_image_shortcode(): string {
		$alt = esc_attr__( 'AI-assisted WordPress websites on laptop and mobile devices', \ASC_AI_EXAMPLE_TEXT_DOMAIN );
		$attachment_id = Media::find_attachment_id_by_media_path( 'hero.jpg' );
		if ( $attachment_id > 0 ) {
			return (string) wp_get_attachment_image(
				$attachment_id,
				'full',
				false,
				array(
					'class' => 'example-hero-image',
					'alt' => $alt,
					'loading' => 'eager',
					'fetchpriority' => 'high',
					'decoding' => 'async',
					'sizes' => '(max-width: 1400px) 100vw, 1400px',
				)
			);
		}

		$url = Media::get_attachment_url_for_path( 'hero.jpg' );
		if ( '' === $url ) {
			return '';
		}

		return '<img class="example-hero-image" src="' . esc_url( $url ) . '" alt="' . $alt . '" width="1440" height="465" loading="eager" fetchpriority="high" decoding="async">';
	}

	/**
	 * Site home URL for partials (no trailing slash).
	 *
	 * @return string
	 */
	public function render_example_home_url_shortcode(): string {
		return esc_url( untrailingslashit( home_url() ) );
	}

	/**
	 * Render header from the Partials CPT.
	 *
	 * @return string
	 */
	public function render_header_shortcode(): string {
		$raw = PartialStore::get_raw_markup( PartialCatalog::KEY_HEADER );
		if ( '' === trim( $raw ) ) {
			return '';
		}

		$raw = str_replace( '[example_home_url]', esc_url( untrailingslashit( home_url() ) ), $raw );
		return do_shortcode( $raw );
	}

	/**
	 * Render footer from the Partials CPT.
	 *
	 * @return string
	 */
	public function render_footer_shortcode(): string {
		$raw = PartialStore::get_raw_markup( PartialCatalog::KEY_FOOTER );
		if ( '' === trim( $raw ) ) {
			return '';
		}

		$raw = str_replace( '[example_home_url]', esc_url( untrailingslashit( home_url() ) ), $raw );
		return do_shortcode( $raw );
	}

	/**
	 * Agency boiler section preceded by divider.
	 *
	 * @return string
	 */
	public function render_boiler_agency_shortcode(): string {
		return Front::get_boiler_section_markup( PartialCatalog::KEY_AGENCY_BOILER );
	}

	/**
	 * Blog boiler section preceded by divider.
	 *
	 * @return string
	 */
	public function render_blog_boiler_shortcode(): string {
		return Front::get_boiler_section_markup( PartialCatalog::KEY_BLOG_BOILER );
	}

	/**
	 * Social media icon row.
	 *
	 * @return string
	 */
	public function render_social_links_shortcode(): string {
		$raw = PartialStore::get_raw_markup( PartialCatalog::KEY_SOCIAL_LINKS );
		if ( '' === trim( $raw ) ) {
			return '';
		}

		return do_shortcode( $raw );
	}

	/**
	 * @return string
	 */
	public function render_about_image_shortcode(): string {
		$url = Media::get_attachment_url_for_path( 'about-us.jpg' );
		if ( '' === $url ) {
			return '';
		}

		return '<img class="example-page-clipart-image" src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'Cozy office workspace with team collaboration illustration', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '" width="640" height="640" loading="lazy" decoding="async">';
	}

	/**
	 * @return string
	 */
	public function render_contact_image_shortcode(): string {
		$url = Media::get_attachment_url_for_path( 'contact-us.jpg' );
		if ( '' === $url ) {
			return '';
		}

		return '<img class="example-page-clipart-image" src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'Cozy office workspace desk showing contact channels illustration', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '" width="640" height="640" loading="lazy" decoding="async">';
	}

	public function render_theme_toggle_shortcode(): string {
		return '<span class="example-theme-toggle" role="group" aria-label="'
			. esc_attr__( 'Theme', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
			. '">'
			. '<button type="button" class="example-theme-toggle-btn example-theme-toggle-btn--light" aria-pressed="false" aria-label="'
			. esc_attr__( 'Light theme', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
			. '"><svg class="example-theme-toggle-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 3V4M12 20V21M4 12H3M6.31412 6.31412L5.5 5.5M17.6859 6.31412L18.5 5.5M6.31412 17.69L5.5 18.5001M17.6859 17.69L18.5 18.5001M21 12H20M16 12C16 14.2091 14.2091 16 12 16C9.79086 16 8 14.2091 8 12C8 9.79086 9.79086 8 12 8C14.2091 8 16 9.79086 16 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>'
			. '<button type="button" class="example-theme-toggle-btn example-theme-toggle-btn--dark" aria-pressed="false" aria-label="'
			. esc_attr__( 'Dark theme', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
			. '"><svg class="example-theme-toggle-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path fill-rule="evenodd" clip-rule="evenodd" d="M8.23129 2.24048C9.24338 1.78695 10.1202 2.81145 9.80357 3.70098C8.72924 6.71928 9.38932 10.1474 11.6193 12.3765C13.8606 14.617 17.3114 15.2755 20.3395 14.1819C21.2206 13.8637 22.2173 14.7319 21.7817 15.7199C21.7688 15.7491 21.7558 15.7782 21.7427 15.8074C20.9674 17.5266 19.7272 19.1434 18.1227 20.2274C16.4125 21.3828 14.3957 22.0001 12.3316 22.0001H12.3306C9.93035 21.9975 7.6057 21.1603 5.75517 19.6321C3.90463 18.1039 2.64345 15.9797 2.18793 13.6237C1.73241 11.2677 2.11094 8.82672 3.2586 6.71917C4.34658 4.72121 6.17608 3.16858 8.20153 2.25386L8.23129 2.24048Z" fill="currentColor"></path></svg></button>'
			. '</span>';
	}

	public function render_icon_shortcode( array|string $attributes ): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}
		$attributes = shortcode_atts(
			array(
				'name' => '',
				'class' => '',
			),
			$attributes,
			'example_icon'
		);

		return Front::icon_svg(
			sanitize_key( (string) $attributes['name'] ),
			sanitize_html_class( (string) $attributes['class'] )
		);
	}
}
