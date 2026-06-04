<?php
/**
 * Thin boiler core: text domain, plugin paths, lifecycle hooks, classic editor, theme shell, and admin bootstrap.
 *
 * Public front-end layout uses {@see ThemeShell} (minimal PHP template plus header/footer filters). Product
 * layers supply partial markup via those filters. {@see ClassicEditor} disables the block editor for all post
 * types unless a filter opts a type back in. In the dashboard, {@see \ASC\AI_BOILER\Admin\Admin} owns Backup /
 * Restore and partial CPT admin helpers. The partial CPT is registered via {@see RegisterPartials}.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Core;

use ASC\AI_BOILER\Admin\Admin as BoilerAdmin;

/**
 * Boiler Core (singleton).
 */
class Core {

	public const VERSION = '1.0.0';

	private static ?Core $instance = null;

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( RegisterPartials::class, 'register' ), 5 );
		ClassicEditor::register();
		ThemeShell::register();
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
		return plugin_dir_url( \ASC_AI_BOILER_PLUGIN_FILE );
	}

	public function get_plugin_path(): string {
		return plugin_dir_path( \ASC_AI_BOILER_PLUGIN_FILE );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			\ASC_AI_BOILER_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages'
		);
	}

	/**
	 * Boiler activation lifecycle.
	 *
	 * @return void
	 */
	public static function activate(): void {
		RegisterPartials::register();
	}

	public static function deactivate(): void {
	}

	public static function uninstall(): void {
	}
}
