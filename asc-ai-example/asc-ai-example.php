<?php

declare( strict_types = 1 );

/**
 * aS.c AI Example
 *
 * Example site plugin for the aS.c AI Boiler framework: services/projects post types,
 * shortcodes, blog and listing UI, admin screens, content sync profile, and bundled assets.
 *
 * Requires the aS.c AI Plugin to be active.
 *
 * @package asc-ai-example
 *
 * @wordpress-plugin
 * Plugin Name: aS.c AI Example
 * Plugin URI: https://asolution.company
 * Description: Example site layer for the aS.c AI Plugin: post types, shortcodes, content sync, admin screens, and front-end assets.
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

if ( ! defined( 'WPINC' ) ) {
	exit;
}

define( 'ASC_AI_EXAMPLE_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ASC_AI_BOILER_TEXT_DOMAIN' ) ) {
	define( 'ASC_AI_BOILER_TEXT_DOMAIN', 'asc-ai-boiler' );
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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( class_exists( ExampleCore\Core::class ) ) {
			ExampleCore\Core::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		if ( class_exists( ExampleCore\Core::class ) ) {
			ExampleCore\Core::deactivate();
		}
	}
);

register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\asc_ai_example_uninstall' );

function asc_ai_example_uninstall(): void {
	if ( class_exists( ExampleCore\Core::class ) ) {
		ExampleCore\Core::uninstall();
	}
}

if ( class_exists( ExampleCore\Core::class ) ) {
	ExampleCore\Core::get_instance();
}
