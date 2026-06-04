<?php
/**
 * Registers all example site shortcodes in one place (handlers remain on domain classes).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleFront;

/**
 * Central shortcode registration.
 */
class RegisterShortcodes {

	/**
	 * @param SiteFront $site_front Site shell shortcodes (header, footer, boiler, social links, image helpers).
	 * @param CallToAction $call_to_action CTA bands (e.g. home Request Quote).
	 * @param BlogFront $blog_front Blog listings and single-post filter.
	 * @param ProjectsFront $projects_front Projects sections and single-project filter.
	 * @param ServicesFront $services_front Services sections and single-service filter.
	 */
	public function __construct(
		SiteFront $site_front,
		CallToAction $call_to_action,
		BlogFront $blog_front,
		ProjectsFront $projects_front,
		ServicesFront $services_front
	) {
		$this->register_site( $site_front, $call_to_action );
		$this->register_blog( $blog_front );
		$this->register_projects( $projects_front );
		$this->register_services( $services_front );
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

	/**
	 * @param ProjectsFront $projects_front Projects front.
	 *
	 * @return void
	 */
	private function register_projects( ProjectsFront $projects_front ): void {
		add_shortcode( 'example_home_featured_project', array( $projects_front, 'render_featured_project_shortcode' ) );
		add_shortcode( 'example_all_projects', array( $projects_front, 'shortcode_all_projects' ) );
	}

	/**
	 * @param ServicesFront $services_front Services front.
	 *
	 * @return void
	 */
	private function register_services( ServicesFront $services_front ): void {
		add_shortcode( 'example_home_featured_service', array( $services_front, 'render_featured_service_shortcode' ) );
		add_shortcode( 'example_services', array( $services_front, 'render_all_services_shortcode' ) );
		add_shortcode( 'example_footer_services', array( $services_front, 'render_footer_services_shortcode' ) );
	}
}
