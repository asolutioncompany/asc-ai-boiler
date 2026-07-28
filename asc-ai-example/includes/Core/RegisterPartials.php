<?php
/**
 * Registers the generic partial CPT for asc-ai-example (dashboard UI for HTML fragments keyed by post meta).
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.0
 * Partial custom post type.
 */
final class RegisterPartials {

	private static bool $runtime_hooks_added = false;

	/** Max 20 characters (WordPress {@see register_post_type()} limit). */
	public const POST_TYPE = 'asc_boiler_partial';

	public const META_PARTIAL_KEY = '_asc_ai_boiler_partial_key';

	/**
	 * Filter: parent admin menu slug for Partials, or `true` for a top-level menu (default).
	 *
	 * @var string
	 */
	public const FILTER_ADMIN_MENU_PARENT = 'asc_ai_boiler_partial_admin_menu_parent';

	/**
	 * Register post type on init.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		$labels = array(
			'name' => __( 'Partials', 'asc-ai-example' ),
			'singular_name' => __( 'Partial', 'asc-ai-example' ),
			'add_new' => __( 'Add New', 'asc-ai-example' ),
			'add_new_item' => __( 'Add New Partial', 'asc-ai-example' ),
			'edit_item' => __( 'Edit Partial', 'asc-ai-example' ),
			'new_item' => __( 'New Partial', 'asc-ai-example' ),
			'view_item' => __( 'View Partial', 'asc-ai-example' ),
			'search_items' => __( 'Search Partials', 'asc-ai-example' ),
			'not_found' => __( 'No partials found.', 'asc-ai-example' ),
			'not_found_in_trash' => __( 'No partials found in Trash.', 'asc-ai-example' ),
		);

		$menu_parent = apply_filters( self::FILTER_ADMIN_MENU_PARENT, true );
		if ( ! is_bool( $menu_parent ) && ! is_string( $menu_parent ) ) {
			$menu_parent = true;
		}

		$show_in_menu = true;
		if ( is_string( $menu_parent ) && '' !== $menu_parent ) {
			$show_in_menu = false;
			add_action(
				'admin_menu',
				static function () use ( $menu_parent ): void {
					self::register_admin_submenu( $menu_parent );
				},
				60
			);
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => $labels,
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => $show_in_menu,
				'menu_position' => 56,
				'menu_icon' => 'dashicons-editor-code',
				'show_in_admin_bar' => false,
				'exclude_from_search' => true,
				'publicly_queryable' => false,
				'has_archive' => false,
				'hierarchical' => false,
				'capability_type' => 'post',
				'supports' => array( 'title', 'editor' ),
				'rewrite' => false,
				'query_var' => false,
			)
		);

		if ( is_admin() && ! self::$runtime_hooks_added ) {
			add_action( 'admin_init', array( self::class, 'require_manage_options_for_admin_screens' ), 1 );
			self::$runtime_hooks_added = true;
		}
	}

	/**
	 * Add Partials list screen under an existing admin menu (parent must already exist).
	 *
	 * @param string $parent_slug Parent menu slug (e.g. example-settings).
	 *
	 * @return void
	 */
	public static function register_admin_submenu( string $parent_slug ): void {
		if ( '' === $parent_slug ) {
			return;
		}

		$obj = get_post_type_object( self::POST_TYPE );
		$menu_title = __( 'Partials', 'asc-ai-example' );
		if ( $obj instanceof \WP_Post_Type && isset( $obj->labels->name ) ) {
			$menu_title = $obj->labels->name;
		}

		add_submenu_page(
			$parent_slug,
			$menu_title,
			$menu_title,
			'manage_options',
			'edit.php?post_type=' . self::POST_TYPE
		);
	}

	/**
	 * Limit Partials list/new/edit screens to administrators (manage_options).
	 *
	 * @return void
	 */
	public static function require_manage_options_for_admin_screens(): void {
		if ( ! is_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}

		global $pagenow;

		if ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) ) {
			$post_type = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) );
			if ( self::POST_TYPE === $post_type ) {
				wp_die(
					esc_html__( 'You do not have permission to access this page.', 'asc-ai-example' ),
					esc_html__( 'Forbidden', 'asc-ai-example' ),
					403
				);
			}
		}

		if ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) ) {
			$post_type = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) );
			if ( self::POST_TYPE === $post_type ) {
				wp_die(
					esc_html__( 'You do not have permission to access this page.', 'asc-ai-example' ),
					esc_html__( 'Forbidden', 'asc-ai-example' ),
					403
				);
			}
		}

		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
			$post_id = absint( wp_unslash( $_GET['post'] ) );
			if ( $post_id > 0 && self::POST_TYPE === get_post_type( $post_id ) ) {
				wp_die(
					esc_html__( 'You do not have permission to access this page.', 'asc-ai-example' ),
					esc_html__( 'Forbidden', 'asc-ai-example' ),
					403
				);
			}
		}
	}
}
