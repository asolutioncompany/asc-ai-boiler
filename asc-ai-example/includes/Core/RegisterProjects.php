<?php
/**
 * Register Projects Post Type
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Projects Post Type Class
 */
class RegisterProjects {

	/**
	 * Projects post type key.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'example_project';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 100 );
	}

	/**
	 * Register Projects custom post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		if ( post_type_exists( 'project' ) ) {
			unregister_post_type( 'project' );
		}

		$labels = array(
			'name'          => __( 'Projects', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'singular_name' => __( 'Project', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'add_new_item'  => __( 'Add New Project', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'edit_item'     => __( 'Edit Project', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'new_item'      => __( 'New Project', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'view_item'     => __( 'View Project', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			'search_items'  => __( 'Search Projects', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => true,
			'show_in_rest'    => true,
			'has_archive'     => false,
			'rewrite'         => array( 'slug' => 'projects' ),
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'taxonomies'      => array( 'category', 'post_tag' ),
			'menu_position'   => 20,
			'menu_icon'       => 'dashicons-portfolio',
			'show_in_menu'    => true,
			'show_ui'         => true,
			'publicly_queryable' => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
