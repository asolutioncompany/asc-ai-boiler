<?php
/**
 * Product core: CPTs, front, and Example admin (excluding boiler sync UI).
 *
 * @package asc-ai-min-example
 */

declare( strict_types = 1 );

namespace ASC\AI_MIN_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Admin\ContentSync;
use ASC\AI_BOILER\Admin\SyncConfig;
use ASC\AI_MIN_EXAMPLE\Admin\Admin;
use ASC\AI_MIN_EXAMPLE\Front\Front;

/**
 * Minimum example product Core (singleton).
 */
class Core {

	private static ?Core $instance = null;

	private function __construct() {
		BoilerIntegration::register();
		ContentSync::ensure_content_directories_exist();
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
		ContentSync::ensure_content_directories_exist();
		flush_rewrite_rules();
		add_option( 'min_example_site_version', \ASC\AI_BOILER\Core\Core::VERSION );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		delete_option( 'min_example_site_version' );
		delete_option( 'min_example_site_development_mode' );
		SyncConfig::delete_all_sync_options();
	}
}
