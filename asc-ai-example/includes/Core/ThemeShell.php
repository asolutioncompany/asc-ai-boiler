<?php
/**
 * Example product hooks for boiler {@see \ASC\AI_BOILER\Core\ThemeShell} (header/footer/CTA partials).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

use ASC\AI_BOILER\Core\ThemeShell as BoilerThemeShell;
use ASC\AI_EXAMPLE\Front\CallToAction;
use ASC\AI_EXAMPLE\Front\SiteFront;

/**
 * Supplies example partial markup to the boiler theme shell.
 */
final class ThemeShell {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( BoilerThemeShell::FILTER_HEADER, array( self::class, 'filter_header_markup' ), 10, 1 );
		add_filter( BoilerThemeShell::FILTER_FOOTER, array( self::class, 'filter_footer_markup' ), 10, 1 );
		add_filter( BoilerThemeShell::FILTER_AFTER_MAIN, array( self::class, 'filter_after_main_markup' ), 10, 1 );
		add_filter( BoilerThemeShell::FILTER_MAIN_CLASS, array( self::class, 'filter_main_class' ), 10, 1 );
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


}
