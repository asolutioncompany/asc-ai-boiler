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
		$url = Media::get_attachment_url_for_path( 'hero.jpg' );
		if ( '' === $url ) {
			return '';
		}

		return '<img class="example-hero-image" src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'AI-assisted WordPress websites on laptop and mobile devices', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '" width="1440" height="465" loading="eager" fetchpriority="high" decoding="async">';
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
			. '"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></button>'
			. '<button type="button" class="example-theme-toggle-btn example-theme-toggle-btn--dark" aria-pressed="false" aria-label="'
			. esc_attr__( 'Dark theme', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
			. '"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>'
			. '</span>';
	}
}
