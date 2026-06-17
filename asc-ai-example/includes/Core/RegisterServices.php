<?php
/**
 * Register Services Post Type
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

/**
 * Register Services Post Type Class
 */
class RegisterServices {

	/**
	 * Services post type key.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'example_service';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 100 );
	}

	/**
	 * Register Services custom post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'          => __( 'Services', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'singular_name' => __( 'Service', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'add_new_item'  => __( 'Add New Service', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'edit_item'     => __( 'Edit Service', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'new_item'      => __( 'New Service', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'view_item'     => __( 'View Service', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'search_items'  => __( 'Search Services', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_in_rest'       => true,
			'has_archive'        => false,
			'rewrite'            => array( 'slug' => 'services' ),
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'taxonomies'         => array( 'category', 'post_tag' ),
			'menu_position'      => 21,
			'menu_icon'          => 'dashicons-hammer',
			'show_in_menu'       => true,
			'show_ui'            => true,
			'publicly_queryable' => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
