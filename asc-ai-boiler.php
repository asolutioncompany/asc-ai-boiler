<?php

declare( strict_types = 1 );

/**
 * aS.c AI Boiler
 *
 * Boilerplate plugin with an example site layer: partial-based layouts, shortcodes,
 * services/projects post types, blog and listing UI, admin settings, and bundled assets.
 *
 * @package asc-ai-boiler
 *
 * @wordpress-plugin
 * Plugin Name: aS.c AI Boiler
 * Plugin URI: https://asolution.company
 * Description: WordPress boilerplate plugin with an example site demonstrating partials, shortcodes, content sync, custom post types, and front-end assets.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Author: Keith Gardner, aSolution.company
 * Author URI: https://asolution.company
 * Text Domain: asc-ai-boiler
 * Domain Path: /languages
 */

namespace ASC\AI_BOILER;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	exit;
}

define( 'ASC_AI_BOILER_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ASC_AI_BOILER_TEXT_DOMAIN' ) ) {
	define( 'ASC_AI_BOILER_TEXT_DOMAIN', 'asc-ai-boiler' );
}
if ( ! defined( 'ASC_AI_BOILER_ENABLE_PRODUCT' ) ) {
	define( 'ASC_AI_BOILER_ENABLE_PRODUCT', true );
}

if ( ! defined( 'ASC_AI_EXAMPLE_TEST_PAGING' ) ) {
	if ( defined( 'ASC_AI_BOILER_TEST_PAGING' ) ) {
		define( 'ASC_AI_EXAMPLE_TEST_PAGING', (bool) ASC_AI_BOILER_TEST_PAGING );
	} else {
		define( 'ASC_AI_EXAMPLE_TEST_PAGING', false );
	}
}
if ( ! defined( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL' ) ) {
	if ( defined( 'ASC_AI_BOILER_TEST_VIEW_ALL' ) ) {
		define( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL', (bool) ASC_AI_BOILER_TEST_VIEW_ALL );
	} else {
		define( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL', false );
	}
}
if ( ! defined( 'ASC_AI_EXAMPLE_TEST_PAGING_POST_NUM' ) ) {
	if ( defined( 'ASC_AI_BOILER_TEST_PAGING_POST_NUM' ) ) {
		define( 'ASC_AI_EXAMPLE_TEST_PAGING_POST_NUM', (int) ASC_AI_BOILER_TEST_PAGING_POST_NUM );
	} else {
		define( 'ASC_AI_EXAMPLE_TEST_PAGING_POST_NUM', 3 );
	}
}
if ( ! defined( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL_NUM' ) ) {
	if ( defined( 'ASC_AI_BOILER_TEST_VIEW_ALL_NUM' ) ) {
		define( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL_NUM', (int) ASC_AI_BOILER_TEST_VIEW_ALL_NUM );
	} else {
		define( 'ASC_AI_EXAMPLE_TEST_VIEW_ALL_NUM', 2 );
	}
}

// Load Composer autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook(
	__FILE__,
	static function (): void {
		Core\Core::activate();
		if ( ASC_AI_BOILER_ENABLE_PRODUCT && class_exists( ExampleCore\Core::class ) ) {
			ExampleCore\Core::activate();
		}
	}
);
register_deactivation_hook(
	__FILE__,
	static function (): void {
		Core\Core::deactivate();
		if ( ASC_AI_BOILER_ENABLE_PRODUCT && class_exists( ExampleCore\Core::class ) ) {
			ExampleCore\Core::deactivate();
		}
	}
);
register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\asc_ai_boiler_uninstall_plugin' );

/**
 * Runs boiler and product uninstall cleanup (callable name required for uninstall hook storage).
 *
 * @return void
 */
function asc_ai_boiler_uninstall_plugin(): void {
	Core\Core::uninstall();
	if ( ASC_AI_BOILER_ENABLE_PRODUCT && class_exists( ExampleCore\Core::class ) ) {
		ExampleCore\Core::uninstall();
	}
}

$asc_ai_boiler = Core\Core::get_instance();
if ( ASC_AI_BOILER_ENABLE_PRODUCT && class_exists( ExampleCore\Core::class ) ) {
	$example_site = ExampleCore\Core::get_instance();
}
