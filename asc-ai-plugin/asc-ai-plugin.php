<?php

declare( strict_types = 1 );

/**
 * aS.c AI Plugin
 *
 * Boilerplate framework plugin: partial-based layouts, content sync, admin settings,
 * classic editor enforcement, and bundled admin assets.
 *
 * @package asc-ai-plugin
 *
 * @wordpress-plugin
 * Plugin Name: aS.c AI Plugin
 * Plugin URI: https://asolution.company
 * Description: WordPress boilerplate framework plugin providing partials, content sync, admin UI, and theme shell.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Author: Keith Gardner, aSolution.company
 * Author URI: https://asolution.company
 * Text Domain: asc-ai-plugin
 * Domain Path: /languages
 */

namespace ASC\AI_BOILER;

if ( ! defined( 'WPINC' ) ) {
	exit;
}

define( 'ASC_AI_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ASC_AI_PLUGIN_DOMAIN' ) ) {
	define( 'ASC_AI_PLUGIN_DOMAIN', 'asc-ai-plugin' );
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook(
	__FILE__,
	static function (): void {
		Core\Core::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Core\Core::deactivate();
	}
);

register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\asc_ai_plugin_uninstall' );

function asc_ai_plugin_uninstall(): void {
	Core\Core::uninstall();
}

Core\Core::get_instance();
