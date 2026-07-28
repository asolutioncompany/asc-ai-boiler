<?php
/**
 * Portfolio Admin Class
 *
 * @package asc-ai-example
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\PostMeta;
use ASC\AI_EXAMPLE\Core\RegisterPortfolio;

/**
 * Portfolio Admin Class
 */
class PortfolioAdmin {

	private const GALLERY_NONCE_ACTION = 'example_site_portfolio_gallery';
	private const GALLERY_NONCE_NAME = 'example_site_portfolio_gallery_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_' . RegisterPortfolio::POST_TYPE, array( $this, 'register_gallery_meta_box' ), 20 );
		add_action( 'save_post_' . RegisterPortfolio::POST_TYPE, array( $this, 'save_gallery_meta' ), 10, 1 );
	}

	/**
	 * Register gallery meta box.
	 *
	 * @return void
	 */
	public function register_gallery_meta_box(): void {
		add_meta_box(
			'example_portfolio_gallery_meta_box',
			__( 'Additional Photos', 'asc-ai-example' ),
			array( $this, 'render_gallery_meta_box' ),
			RegisterPortfolio::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render gallery meta box.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_gallery_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::GALLERY_NONCE_ACTION, self::GALLERY_NONCE_NAME );

		$attachment_ids = PostMeta::parse_portfolio_gallery_attachment_ids(
			get_post_meta( $post->ID, PostMeta::PORTFOLIO_GALLERY_META_KEY, true )
		);
		$stored_value = implode( ',', $attachment_ids );

		echo '<p class="description">';
		echo esc_html(
			sprintf(
				/* translators: %d: maximum number of gallery photos */
				__( 'Add up to %d additional photos beyond the featured image.', 'asc-ai-example' ),
				PostMeta::PORTFOLIO_GALLERY_MAX_PHOTOS
			)
		);
		echo '</p>';

		echo '<div class="example-project-gallery-admin" data-max="' . esc_attr( (string) PostMeta::PORTFOLIO_GALLERY_MAX_PHOTOS ) . '">';
		echo '<input type="hidden" class="example-project-gallery-admin__ids" name="example_site_portfolio_gallery" value="' . esc_attr( $stored_value ) . '">';
		echo '<ul class="example-project-gallery-admin__list">';

		foreach ( $attachment_ids as $attachment_id ) {
			$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
			if ( ! is_string( $thumb_url ) || '' === $thumb_url ) {
				continue;
			}

			echo '<li class="example-project-gallery-admin__item" data-id="' . esc_attr( (string) $attachment_id ) . '">';
			echo '<img src="' . esc_url( $thumb_url ) . '" alt="">';
			echo '<button type="button" class="button-link example-project-gallery-admin__remove">' . esc_html__( 'Remove', 'asc-ai-example' ) . '</button>';
			echo '</li>';
		}

		echo '</ul>';
		echo '<p><button type="button" class="button example-project-gallery-admin__add">' . esc_html__( 'Add photos', 'asc-ai-example' ) . '</button></p>';
		echo '</div>';
	}

	/**
	 * Save gallery meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function save_gallery_meta( int $post_id ): void {
		if ( ! isset( $_POST[ self::GALLERY_NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::GALLERY_NONCE_NAME ] ) ), self::GALLERY_NONCE_ACTION ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = '';
		if ( isset( $_POST['example_site_portfolio_gallery'] ) ) {
			$raw = sanitize_text_field( wp_unslash( (string) $_POST['example_site_portfolio_gallery'] ) );
		}

		$attachment_ids = PostMeta::parse_portfolio_gallery_attachment_ids( $raw );
		update_post_meta( $post_id, PostMeta::PORTFOLIO_GALLERY_META_KEY, implode( ',', $attachment_ids ) );
	}
}
