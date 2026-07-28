<?php
/**
 * Content Synchronization Tool Core: text domain, plugin paths, lifecycle hooks, and admin bootstrap.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Admin\Admin as BoilerAdmin;

/**
 * Boiler Core (singleton).
 */
class Core {

	public const VERSION = '1.0.0';

	private static ?Core $instance = null;

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		if ( is_admin() ) {
			new BoilerAdmin();
		}
	}

	public static function get_instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_plugin_url(): string {
		return plugin_dir_url( \ASC_AI_PLUGIN_FILE );
	}

	public function get_plugin_path(): string {
		return plugin_dir_path( \ASC_AI_PLUGIN_FILE );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			\ASC_AI_PLUGIN_DOMAIN,
			false,
			dirname( plugin_basename( \ASC_AI_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Boiler activation lifecycle.
	 *
	 * @return void
	 */
	public static function activate(): void {
	}

	public static function deactivate(): void {
	}

	public static function uninstall(): void {
	}
}
