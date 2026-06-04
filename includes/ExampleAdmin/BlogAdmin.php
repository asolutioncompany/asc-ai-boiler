<?php
/**
 * Blog Admin Class
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleAdmin;

use ASC\AI_BOILER\ExampleCore\PostMeta;

/**
 * Blog Admin Class
 */
class BlogAdmin {

	private const NONCE_ACTION = 'example_site_blog_tag_settings';
	private const NONCE_NAME = 'example_site_blog_tag_nonce';
	private const FIXED_TAGS = array( 'article', 'in-the-news', 'community-outreach' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_post', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_tag_meta' ), 10, 1 );
	}

	/**
	 * Register blog tag settings meta box.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'example_site_blog_settings',
			__( 'Blog Settings', \ASC_AI_BOILER_TEXT_DOMAIN ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * Render blog tag settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$selected_tag = $this->get_selected_tag_slug( (int) $post->ID );
		echo Admin::get_tag_toggle_group_html( 'blog', $selected_tag, self::FIXED_TAGS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$post_id = (int) $post->ID;
		$link_label = (string) get_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_LABEL_META_KEY, true );
		$link_url = (string) get_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_URL_META_KEY, true );

		echo '<div class="example-blog-meta-link" style="margin-top:12px;">';
		echo '<p><strong>' . esc_html__( 'Link', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</strong></p>';
		echo '<p><label for="example_blog_cta_link_label">' . esc_html__( 'Label', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</label></p>';
		echo '<p><input type="text" class="widefat" id="example_blog_cta_link_label" name="example_blog_cta_link_label" value="' . esc_attr( $link_label ) . '" autocomplete="off" /></p>';
		echo '<p><label for="example_blog_cta_link_url">' . esc_html__( 'Link', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</label></p>';
		echo '<p><input type="url" class="widefat" id="example_blog_cta_link_url" name="example_blog_cta_link_url" value="' . esc_attr( $link_url ) . '" placeholder="https://" autocomplete="off" /></p>';
		echo '</div>';
	}

	/**
	 * Resolve selected fixed tag from meta or assigned terms.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_selected_tag_slug( int $post_id ): string {
		$selected_tag = (string) get_post_meta( $post_id, PostMeta::PRIMARY_TAG_META_KEY, true );
		if ( in_array( $selected_tag, self::FIXED_TAGS, true ) ) {
			return $selected_tag;
		}

		$tags = get_the_terms( $post_id, 'post_tag' );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				if ( in_array( $tag->slug, self::FIXED_TAGS, true ) ) {
					return $tag->slug;
				}
			}
		}

		if ( 'auto-draft' === get_post_status( $post_id ) ) {
			return 'article';
		}

		return '';
	}

	/**
	 * Save blog tag settings meta.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function save_tag_meta( int $post_id ): void {
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

		$selected_tag = '';
		foreach ( self::FIXED_TAGS as $tag_slug ) {
			$post_key = 'example_site_tag_' . $tag_slug;
			if ( isset( $_POST[ $post_key ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) ) {
				$selected_tag = $tag_slug;
				break;
			}
		}

		update_post_meta( $post_id, PostMeta::PRIMARY_TAG_META_KEY, $selected_tag );
		if ( '' !== $selected_tag ) {
			wp_set_post_terms( $post_id, array( $selected_tag ), 'post_tag', false );
		} else {
			wp_remove_object_terms( $post_id, self::FIXED_TAGS, 'post_tag' );
		}

		$cta_label = '';
		if ( isset( $_POST['example_blog_cta_link_label'] ) ) {
			$cta_label = sanitize_text_field( wp_unslash( (string) $_POST['example_blog_cta_link_label'] ) );
		}

		$cta_url_raw = '';
		if ( isset( $_POST['example_blog_cta_link_url'] ) ) {
			$cta_url_raw = trim( wp_unslash( (string) $_POST['example_blog_cta_link_url'] ) );
		}
		$cta_url = '' !== $cta_url_raw ? esc_url_raw( $cta_url_raw ) : '';

		if ( '' === $cta_label && '' === $cta_url ) {
			delete_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_LABEL_META_KEY );
			delete_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_URL_META_KEY );
		} else {
			update_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_LABEL_META_KEY, $cta_label );
			if ( '' !== $cta_url ) {
				update_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_URL_META_KEY, $cta_url );
			} else {
				delete_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_URL_META_KEY );
			}
		}
	}
}
