<?php
/**
 * Register Portfolio Post Type
 *
 * @package asc-ai-example
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Portfolio Post Type Class
 */
class RegisterPortfolio {

	/**
	 * Portfolio post type key.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'example_portfolio';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 100 );
	}

	/**
	 * Register Portfolio custom post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		if ( post_type_exists( 'portfolio' ) ) {
			unregister_post_type( 'portfolio' );
		}

		$labels = array(
			'name' => __( 'Portfolio', 'asc-ai-example' ),
			'singular_name' => __( 'Portfolio Item', 'asc-ai-example' ),
			'add_new' => __( 'Add New', 'asc-ai-example' ),
			'add_new_item' => __( 'Add New Portfolio Item', 'asc-ai-example' ),
			'edit_item' => __( 'Edit Portfolio Item', 'asc-ai-example' ),
			'new_item' => __( 'New Portfolio Item', 'asc-ai-example' ),
			'view_item' => __( 'View Portfolio Item', 'asc-ai-example' ),
			'search_items' => __( 'Search Portfolio', 'asc-ai-example' ),
			'not_found' => __( 'No portfolio items found.', 'asc-ai-example' ),
		);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'show_in_rest' => true,
			'has_archive' => false,
			'rewrite' => array( 'slug' => 'portfolio' ),
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'taxonomies' => array( 'category', 'post_tag' ),
			'menu_position' => 20,
			'menu_icon' => 'dashicons-portfolio',
			'show_in_menu' => true,
			'show_ui' => true,
			'publicly_queryable' => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
