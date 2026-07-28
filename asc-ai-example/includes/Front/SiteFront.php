<?php
/**
 * Site shell: partial shortcodes and home URL helper.
 *
 * @package asc-ai-example
 * @since 1.0.0
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
}
