<?php
/**
 * Blog Admin Class
 *
 * @package asc-ai-example
 * @since 1.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\PostMeta;

/**
 * Blog Admin Class
 */
class BlogAdmin {

	private const NONCE_ACTION = 'example_site_blog_featured';
	private const NONCE_NAME = 'example_site_blog_featured_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_post', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_featured_meta' ), 10, 1 );
		add_filter( 'manage_post_posts_columns', array( $this, 'add_featured_column' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'render_featured_column' ), 10, 2 );
	}

	/**
	 * Register blog settings meta box.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'example_blog_settings_meta_box',
			__( 'Blog Settings', 'asc-ai-example' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * Render blog settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$featured_raw = get_post_meta( (int) $post->ID, PostMeta::FEATURED_META_KEY, true );
		$is_featured = '1' === (string) $featured_raw;

		echo Admin::get_featured_toggle_html( $is_featured ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Save blog featured meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function save_featured_meta( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_featured = isset( $_POST['example_featured'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['example_featured'] ) );

		if ( $is_featured ) {
			update_post_meta( $post_id, PostMeta::FEATURED_META_KEY, '1' );
			PostMeta::clear_featured_meta_on_other_posts( 'post', $post_id );
		} else {
			delete_post_meta( $post_id, PostMeta::FEATURED_META_KEY );
		}
	}

	/**
	 * Add featured column to post list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function add_featured_column( array $columns ): array {
		$columns['example_post_featured'] = __( 'Featured', 'asc-ai-example' );
		return $columns;
	}

	/**
	 * Render featured column value.
	 *
	 * @param string $column Column name.
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function render_featured_column( string $column, int $post_id ): void {
		if ( 'example_post_featured' !== $column ) {
			return;
		}

		$featured_raw = get_post_meta( $post_id, PostMeta::FEATURED_META_KEY, true );
		if ( '1' === (string) $featured_raw ) {
			echo esc_html__( 'Yes', 'asc-ai-example' );
			return;
		}

		echo esc_html__( 'No', 'asc-ai-example' );
	}
}
