<?php
/**
 * Example product hooks for boiler {@see \ASC\AI_BOILER\Core\ThemeShell} (header/footer/CTA partials).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleCore;

use ASC\AI_BOILER\Core\ThemeShell;
use ASC\AI_BOILER\ExampleFront\CallToAction;
use ASC\AI_BOILER\ExampleFront\SiteFront;

/**
 * Supplies example partial markup to the boiler theme shell.
 */
final class ExampleThemeShell {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( ThemeShell::FILTER_HEADER, array( self::class, 'filter_header_markup' ), 10, 1 );
		add_filter( ThemeShell::FILTER_FOOTER, array( self::class, 'filter_footer_markup' ), 10, 1 );
		add_filter( ThemeShell::FILTER_AFTER_MAIN, array( self::class, 'filter_after_main_markup' ), 10, 1 );
		add_filter( ThemeShell::FILTER_MAIN, array( self::class, 'filter_main_markup' ), 10, 1 );
		add_filter( ThemeShell::FILTER_MAIN_CLASS, array( self::class, 'filter_main_class' ), 10, 1 );
	}

	/**
	 * @param string $markup Existing header markup.
	 *
	 * @return string
	 */
	public static function filter_header_markup( string $markup ): string {
		if ( '' !== $markup ) {
			return $markup;
		}

		$site_front = new SiteFront();

		return $site_front->render_header_shortcode();
	}

	/**
	 * @param string $markup Existing footer markup.
	 *
	 * @return string
	 */
	public static function filter_footer_markup( string $markup ): string {
		if ( '' !== $markup ) {
			return $markup;
		}

		$site_front = new SiteFront();

		return $site_front->render_footer_shortcode();
	}

	/**
	 * @param string $markup Existing after-main markup.
	 *
	 * @return string
	 */
	public static function filter_after_main_markup( string $markup ): string {
		$call_to_action = new CallToAction();

		return $markup . $call_to_action->render_cta_shortcode();
	}

	/**
	 * @param string $main_class Default main element class list.
	 *
	 * @return string
	 */
	public static function filter_main_class( string $main_class ): string {
		return 'asc-ai-boiler-main example-main';
	}

	/**
	 * Branded markup for archive, search, and 404 views when the example layer is active.
	 *
	 * @param string|null $markup Custom main markup, or null for boiler default.
	 *
	 * @return string|null
	 */
	public static function filter_main_markup( ?string $markup ): ?string {
		if ( null !== $markup ) {
			return $markup;
		}

		if ( is_singular() ) {
			return null;
		}

		if ( is_404() ) {
			return self::render_system_page_shell(
				__( 'Page not found', \ASC_AI_BOILER_TEXT_DOMAIN ),
				'<p>' . esc_html__( 'The page you requested could not be found.', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</p>'
				. '<p><a class="example-button-blue" href="' . esc_url( home_url( '/' ) ) . '">'
				. esc_html__( 'Return home', \ASC_AI_BOILER_TEXT_DOMAIN )
				. '</a></p>'
			);
		}

		if ( is_search() ) {
			$inner = '<p>' . esc_html__( 'No results found.', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</p>';
			if ( have_posts() ) {
				$inner = '<ul class="example-shell-list">';
				while ( have_posts() ) {
					the_post();
					$inner .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
				}
				$inner .= '</ul>';
				wp_reset_postdata();
			}

			$title = sprintf(
				/* translators: %s: search query */
				__( 'Search results for: %s', \ASC_AI_BOILER_TEXT_DOMAIN ),
				get_search_query()
			);

			return self::render_system_page_shell( $title, $inner );
		}

		if ( is_home() && ! is_front_page() ) {
			$inner = '<p>' . esc_html__( 'No posts found.', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</p>';
			if ( have_posts() ) {
				$inner = '<ul class="example-shell-list">';
				while ( have_posts() ) {
					the_post();
					$inner .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
				}
				$inner .= '</ul>';
				wp_reset_postdata();
			}

			return self::render_system_page_shell( __( 'Blog', \ASC_AI_BOILER_TEXT_DOMAIN ), $inner );
		}

		if ( is_archive() ) {
			$inner = '<p>' . esc_html__( 'No posts found.', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</p>';
			if ( have_posts() ) {
				$inner = '<ul class="example-shell-list">';
				while ( have_posts() ) {
					the_post();
					$inner .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
				}
				$inner .= '</ul>';
				wp_reset_postdata();
			}

			return self::render_system_page_shell_html_title( get_the_archive_title(), $inner );
		}

		return null;
	}

	/**
	 * @param string $title_html Archive title HTML from WordPress.
	 * @param string $inner_html Prepared inner HTML.
	 *
	 * @return string
	 */
	private static function render_system_page_shell_html_title( string $title_html, string $inner_html ): string {
		return '<section class="example-page-content">'
			. '<h1 class="example-page-title">' . $title_html . '</h1>'
			. $inner_html
			. '</section>';
	}

	/**
	 * @param string $title Page heading.
	 * @param string $inner_html Prepared inner HTML.
	 *
	 * @return string
	 */
	private static function render_system_page_shell( string $title, string $inner_html ): string {
		return self::render_system_page_shell_html_title( esc_html( $title ), $inner_html );
	}
}
