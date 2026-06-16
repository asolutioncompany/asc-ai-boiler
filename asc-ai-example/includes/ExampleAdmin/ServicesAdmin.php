<?php
/**
 * Services Admin Class
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleAdmin;

use ASC\AI_BOILER\ExampleCore\PostMeta;
use ASC\AI_BOILER\ExampleCore\RegisterServices;

/**
 * Services Admin Class
 */
class ServicesAdmin {
	/**
	 * Nonce action key.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'example_site_featured_service';

	/**
	 * Nonce name key.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'example_site_featured_nonce_service';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_' . RegisterServices::POST_TYPE, array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . RegisterServices::POST_TYPE, array( $this, 'save_featured_meta' ), 10, 1 );
		add_filter( 'manage_' . RegisterServices::POST_TYPE . '_posts_columns', array( $this, 'add_featured_column' ) );
		add_action( 'manage_' . RegisterServices::POST_TYPE . '_posts_custom_column', array( $this, 'render_featured_column' ), 10, 2 );
	}

	/**
	 * Register featured meta box.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'example_services_service_featured',
			__( 'Service Settings', \ASC_AI_BOILER_TEXT_DOMAIN ),
			array( $this, 'render_meta_box' ),
			RegisterServices::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render featured meta box.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$is_featured = false;
		$featured_raw = get_post_meta( $post->ID, PostMeta::FEATURED_META_KEY, true );
		if ( '1' === (string) $featured_raw ) {
			$is_featured = true;
		}

		$is_new = false;
		$new_raw = get_post_meta( $post->ID, PostMeta::NEW_META_KEY, true );
		if ( '1' === (string) $new_raw ) {
			$is_new = true;
		} elseif ( '0' === (string) $new_raw ) {
			$is_new = false;
		} elseif ( 'auto-draft' === get_post_status( $post ) ) {
			$is_new = true;
		}

		echo Admin::get_featured_toggle_html( $is_featured ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin::get_new_toggle_html( $is_new ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Save featured meta.
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

		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_featured = false;
		if ( isset( $_POST['example_site_featured'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['example_site_featured'] ) ) ) {
			$is_featured = true;
		}

		$is_new = false;
		if ( isset( $_POST['example_site_new'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['example_site_new'] ) ) ) {
			$is_new = true;
		}

		if ( $is_new ) {
			update_post_meta( $post_id, PostMeta::NEW_META_KEY, '1' );
		} else {
			update_post_meta( $post_id, PostMeta::NEW_META_KEY, '0' );
		}

		if ( $is_featured ) {
			update_post_meta( $post_id, PostMeta::FEATURED_META_KEY, '1' );
			PostMeta::clear_featured_meta_on_other_posts( RegisterServices::POST_TYPE, $post_id );
			return;
		}

		update_post_meta( $post_id, PostMeta::FEATURED_META_KEY, '0' );
	}

	/**
	 * Add featured column to post list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function add_featured_column( array $columns ): array {
		$columns['example_site_featured'] = __( 'Featured', \ASC_AI_BOILER_TEXT_DOMAIN );
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
		if ( 'example_site_featured' !== $column ) {
			return;
		}

		$is_featured = false;
		$featured_raw = get_post_meta( $post_id, PostMeta::FEATURED_META_KEY, true );
		if ( '1' === (string) $featured_raw ) {
			$is_featured = true;
		}

		if ( $is_featured ) {
			echo esc_html__( 'Yes', \ASC_AI_BOILER_TEXT_DOMAIN );
			return;
		}

		echo esc_html__( 'No', \ASC_AI_BOILER_TEXT_DOMAIN );
	}
}
