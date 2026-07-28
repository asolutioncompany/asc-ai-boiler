<?php
/**
 * Product core: CPTs, front, and Example admin (excluding boiler sync UI).
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Admin\Admin;
use ASC\AI_EXAMPLE\Front\Front;

/**
 * @since 1.0
 * Minimum example product Core (singleton).
 */
class Core {

	public const VERSION = '1.0';

	private static ?Core $instance = null;

	private function __construct() {
		add_action( 'init', array( RegisterPartials::class, 'register' ), 5 );
		ThemeShell::register();
		BoilerIntegration::register();
		new RegisterPortfolio();
		new Front();
		if ( is_admin() ) {
			new Admin();
		}
	}

	public static function get_instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		RegisterPartials::register();

		$register_portfolio = new RegisterPortfolio();
		$register_portfolio->register_post_type();

		flush_rewrite_rules();
		add_option( 'example_site_version', self::VERSION );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		delete_option( 'example_site_version' );
		delete_option( 'example_site_development_mode' );
	}
}
