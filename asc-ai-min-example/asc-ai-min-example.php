<?php

declare( strict_types = 1 );

/**
 * aS.c Minimum Example
 *
 * Minimum example site layer for the aS.c Boiler framework. This plugin contains no custom post types for projects and services.
 *
 * Requires aS.c Boiler to be active.
 *
 * @package asc-ai-min-example
 *
 * @wordpress-plugin
 * Plugin Name: aS.c Minimum Example
 * Plugin URI: https://asolution.company
 * Description: Minimum example site layer for the aS.c Boiler framework.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Author: Keith Gardner, aSolution.company
 * Author URI: https://asolution.company
 * Text Domain: asc-ai-min-example
 * Domain Path: /languages
 */

namespace ASC\AI_MIN_EXAMPLE;

if ( ! defined( 'WPINC' ) ) {
	exit;
}

define( 'ASC_AI_MIN_EXAMPLE_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN' ) ) {
	define( 'ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN', 'asc-ai-min-example' );
}

// Disable to limit data mining that can impact performance
if ( ! defined( 'ASC_AI_MIN_EXAMPLE_SEARCH_ENABLED' ) ) {
	define( 'ASC_AI_MIN_EXAMPLE_SEARCH_ENABLED', true );
}

// Disable to limit data mining that can impact performance
if ( ! defined( 'ASC_AI_MIN_EXAMPLE_ARCHIVE_ENABLED' ) ) {
	define( 'ASC_AI_MIN_EXAMPLE_ARCHIVE_ENABLED', true );
}

// Card grid excerpt source: 'none' | 'excerpt' | 'meta_description' | 'content'
if ( ! defined( 'ASC_AI_MIN_EXAMPLE_CARD_EXCERPT_SOURCE' ) ) {
	define( 'ASC_AI_MIN_EXAMPLE_CARD_EXCERPT_SOURCE', 'none' );
}

// Word limit for card excerpt when source is 'content' (0 = no limit; takes priority over char limit)
if ( ! defined( 'ASC_AI_MIN_EXAMPLE_CARD_WORD_LIMIT' ) ) {
	define( 'ASC_AI_MIN_EXAMPLE_CARD_WORD_LIMIT', 0 );
}

// Character limit for card excerpt when source is 'content' and word limit is 0 (0 = no limit)
if ( ! defined( 'ASC_AI_MIN_EXAMPLE_CARD_CHAR_LIMIT' ) ) {
	define( 'ASC_AI_MIN_EXAMPLE_CARD_CHAR_LIMIT', 0 );
}


if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( class_exists( Core\Core::class ) ) {
			Core\Core::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		if ( class_exists( Core\Core::class ) ) {
			Core\Core::deactivate();
		}
	}
);

register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\asc_ai_min_example_uninstall' );

function asc_ai_min_example_uninstall(): void {
	if ( class_exists( Core\Core::class ) ) {
		Core\Core::uninstall();
	}
}

add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( Core\Core::class ) ) {
			Core\Core::get_instance();
		}
	}
);
