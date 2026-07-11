<?php
/**
 * Product core: CPTs, front, and Example admin (excluding boiler sync UI). The partial CPT is registered by boiler {@see \ASC\AI_BOILER\Core\Core}.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Admin\ContentSync;
use ASC\AI_BOILER\Admin\SyncConfig;
use ASC\AI_EXAMPLE\Admin\Admin;
use ASC\AI_EXAMPLE\Front\Front;

/**
 * Example product Core (singleton).
 */
class Core {

	private static ?Core $instance = null;

	private function __construct() {
		BoilerIntegration::register();
		ContentSync::ensure_content_directories_exist();
		new RegisterProjects();
		new RegisterServices();
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
		$register_projects = new RegisterProjects();
		$register_projects->register_post_type();

		$register_services = new RegisterServices();
		$register_services->register_post_type();

		ContentSync::ensure_content_directories_exist();

		flush_rewrite_rules();

		add_option( 'example_site_version', \ASC\AI_BOILER\Core\Core::VERSION );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		delete_option( 'example_site_version' );
		delete_option( 'example_site_development_mode' );
		SyncConfig::delete_all_sync_options();
	}
}
