<?php
/**
 * Registers all example site shortcodes in one place.
 *
 * @package asc-ai-min-example
 */

declare( strict_types = 1 );

namespace ASC\AI_MIN_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central shortcode registration.
 */
class RegisterShortcodes {

	/**
	 * @param SiteFront $site_front Site shell shortcodes.
	 * @param CallToAction $call_to_action CTA bands.
	 * @param BlogFront $blog_front Blog listings and single-post filter.
	 */
	public function __construct(
		SiteFront $site_front,
		CallToAction $call_to_action,
		BlogFront $blog_front
	) {
		$this->register_site( $site_front, $call_to_action );
		$this->register_blog( $blog_front );
	}

	/**
	 * @param SiteFront $site_front Site front.
	 * @param CallToAction $call_to_action CTA handlers.
	 *
	 * @return void
	 */
	private function register_site( SiteFront $site_front, CallToAction $call_to_action ): void {
		add_shortcode( 'example_home_url', array( $site_front, 'render_example_home_url_shortcode' ) );
		add_shortcode( 'example_hero_image', array( $site_front, 'render_hero_image_shortcode' ) );
		add_shortcode( 'example_about_image', array( $site_front, 'render_about_image_shortcode' ) );
		add_shortcode( 'example_contact_image', array( $site_front, 'render_contact_image_shortcode' ) );

		add_shortcode( 'example_header', array( $site_front, 'render_header_shortcode' ) );
		add_shortcode( 'example_footer', array( $site_front, 'render_footer_shortcode' ) );
		add_shortcode( 'example_social_links', array( $site_front, 'render_social_links_shortcode' ) );

		add_shortcode( 'example_boiler_agency', array( $site_front, 'render_boiler_agency_shortcode' ) );
		add_shortcode( 'example_blog_boiler', array( $site_front, 'render_blog_boiler_shortcode' ) );

		add_shortcode( 'example_home_cta_request_quote', array( $call_to_action, 'render_home_cta_request_quote_shortcode' ) );
		add_shortcode( 'example_cta', array( $call_to_action, 'render_cta_shortcode' ) );
	}

	/**
	 * @param BlogFront $blog_front Blog front.
	 *
	 * @return void
	 */
	private function register_blog( BlogFront $blog_front ): void {
		add_shortcode( 'example_home_blogs', array( $blog_front, 'shortcode_home_blogs' ) );
		add_shortcode( 'example_all_blogs', array( $blog_front, 'shortcode_all_blogs' ) );
	}
}
