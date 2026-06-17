<?php
/**
 * Site shell: partial shortcodes and home URL helper.
 *
 * Shortcode registration lives in RegisterShortcodes.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

use ASC\AI_BOILER\Core\PartialStore;
use ASC\AI_EXAMPLE\Core\PartialCatalog;

use ASC\AI_BOILER\Core\ContentMediaSync;

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
		$url = ContentMediaSync::get_attachment_url_for_path( 'hero.jpg' );
		if ( '' === $url ) {
			return '';
		}

		return '<img class="example-hero-image" src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'AI-assisted WordPress websites on laptop and mobile devices', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '" width="1440" height="465" loading="eager" fetchpriority="high" decoding="async">';
	}

	/**
	 * Site home URL for partials (no trailing slash). Use like href="[example_home_url]/about-us/".
	 *
	 * @return string
	 */
	public function render_example_home_url_shortcode(): string {
		return esc_url( untrailingslashit( home_url() ) );
	}

	/**
	 * Render header from the Partials CPT (published body only).
	 *
	 * @return string
	 */
	public function render_header_shortcode(): string {
		$raw = PartialStore::get_raw_markup( PartialCatalog::KEY_HEADER );
		if ( '' === trim( $raw ) ) {
			return '';
		}

		return do_shortcode( $raw );
	}

	/**
	 * Render footer from the Partials CPT (published body only).
	 *
	 * @return string
	 */
	public function render_footer_shortcode(): string {
		$raw = PartialStore::get_raw_markup( PartialCatalog::KEY_FOOTER );
		if ( '' === trim( $raw ) ) {
			return '';
		}

		return do_shortcode( $raw );
	}

	/**
	 * Agency boiler section (Partials CPT) preceded by the yellow divider.
	 *
	 * @return string
	 */
	public function render_boiler_agency_shortcode(): string {
		return Front::get_boiler_section_markup( PartialCatalog::KEY_AGENCY_BOILER );
	}

	/**
	 * Blog boiler section (Partials CPT) preceded by the green divider.
	 *
	 * @return string
	 */
	public function render_blog_boiler_shortcode(): string {
		return Front::get_boiler_section_markup( PartialCatalog::KEY_BLOG_BOILER );
	}

	/**
	 * Social media icon row (Partials CPT).
	 *
	 * Used in the footer brand column and the contact-us info panel via the [example_social_links] shortcode.
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
}
