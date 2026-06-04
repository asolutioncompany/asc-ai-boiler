<?php
/**
 * Services Front Class
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleFront;

use ASC\AI_BOILER\Core\ContentMediaSync;
use ASC\AI_BOILER\ExampleCore\ArchiveConfig;
use ASC\AI_BOILER\ExampleCore\CoreSettings;
use ASC\AI_BOILER\ExampleCore\ExampleMediaBindings;
use ASC\AI_BOILER\ExampleCore\PostMeta;
use ASC\AI_BOILER\ExampleCore\RegisterServices;
use WP_Query;

/**
 * Services Front Class
 */
class ServicesFront {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'filter_single_service_content' ), 20 );
	}

	/**
	 * Filter single service content into custom markup.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public function filter_single_service_content( string $content ): string {
		if ( is_admin() || ! is_singular( RegisterServices::POST_TYPE ) ) {
			return $content;
		}

		$request_path = wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! is_string( $request_path ) || 1 !== preg_match( '#^/services/[^/]+/?$#', $request_path ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return $content;
		}

		$tag_markup = Front::get_pill_markup( $post_id );
		$pill_markup = Front::featured_and_new_pill_markup( $post_id );
		$pill_group_markup = '';
		if ( '' !== $pill_markup ) {
			$pill_group_markup = '<div class="example-pills-wrapper">' . $pill_markup . '</div>';
		}
		$media_markup = Front::single_service_icon_media_markup( $post_id );
		$boiler_markup = Front::get_service_boiler_markup();
		$heading_markup = $pill_group_markup
			. $tag_markup
			. '<h1 class="example-post-entry-title">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
		$main_markup = $content
			. $this->render_service_view_all_row(
				ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_SERVICES ),
				esc_html__( 'View All Services', \ASC_AI_BOILER_TEXT_DOMAIN )
			);

		return Front::render_single_entry_article_markup(
			$media_markup,
			$heading_markup,
			'',
			$main_markup,
			$boiler_markup
		);
	}

	/**
	 * Render featured service shortcode.
	 *
	 * @return string
	 */
	public function render_featured_service_shortcode(): string {
		return $this->render_featured_post_markup( RegisterServices::POST_TYPE );
	}

	/**
	 * Render all services shortcode.
	 *
	 * @return string
	 */
	public function render_all_services_shortcode(): string {
		return $this->render_all_services_markup();
	}

	/**
	 * Footer middle column: links to each service with list icon and title.
	 *
	 * @return string
	 */
	public function render_footer_services_shortcode(): string {
		$ordered_ids = $this->get_ordered_service_post_ids();
		if ( array() === $ordered_ids ) {
			return '';
		}

		$items_markup = '';
		foreach ( $ordered_ids as $post_id ) {
			$item_markup = $this->render_footer_service_link_markup( (int) $post_id );
			if ( '' === $item_markup ) {
				continue;
			}
			$items_markup .= '<li>' . $item_markup . '</li>';
		}

		if ( '' === $items_markup ) {
			return '';
		}

		return '<ul class="example-footer-service-list">' . $items_markup . '</ul>';
	}

	/**
	 * Render featured post markup.
	 *
	 * @param string $post_type Post type key.
	 *
	 * @return string
	 */
	private function render_featured_post_markup( string $post_type ): string {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'meta_key'               => PostMeta::FEATURED_META_KEY,
				'meta_value'             => '1',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$post_id = (int) $query->posts[0]->ID;
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		$title = get_the_title( $post_id );
		$content_raw = get_post_field( 'post_content', $post_id );
		if ( ! is_string( $content_raw ) ) {
			$content_raw = '';
		}
		$content_html = Front::first_paragraph_html_from_post_content( $content_raw );
		$read_more_html = Front::read_more_button_html( $permalink );
		$image_html = Front::featured_section_media_link_html_with_setting_key(
			$post_id,
			$title,
			$permalink,
			CoreSettings::SETTING_IMAGE_SERVICES
		);
		$image_html = str_replace( '<a href="', '<a tabindex="-1" href="', $image_html );

		return '<div class="example-section example-page-section example-page-section--dark">'
			. '<div class="example-page-section--media">' . $image_html . '</div>'
			. '<div class="example-page-section--content">'
			. Front::get_pill_markup( $post_id )
			. '<p id="featured-service" class="example-featured-section-label">' . esc_html__( 'Featured Service', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</p>'
			. '<h3 class="example-post-entry-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h3>'
			. '<div class="example-post-entry-body">' . $content_html . '</div>'
			. '<div class="example-featured-read-more">' . $read_more_html . '</div>'
			. '<div class="example-cta-buttons"><a class="example-button-red" href="' . esc_url( ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_SERVICES ) ) . '">' . esc_html__( 'View More Services', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</a></div>'
			. '</div>'
			. '</div>';
	}

	/**
	 * Render services list markup for all published services.
	 *
	 * @return string
	 */
	/**
	 * @return list<int>
	 */
	private function get_ordered_service_post_ids(): array {
		$query = new WP_Query(
			array(
				'post_type'              => RegisterServices::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return array();
		}

		$featured_ids = array();
		$new_ids = array();
		$regular_ids = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = (int) get_the_ID();
			$slug = (string) get_post_field( 'post_name', $post_id );
			if ( 'help' === $slug ) {
				continue;
			}

			$is_featured = '1' === (string) get_post_meta( $post_id, PostMeta::FEATURED_META_KEY, true );
			$is_new = '1' === (string) get_post_meta( $post_id, PostMeta::NEW_META_KEY, true );

			if ( $is_featured ) {
				$featured_ids[] = $post_id;
				continue;
			}

			if ( $is_new ) {
				$new_ids[] = $post_id;
				continue;
			}

			$regular_ids[] = $post_id;
		}

		wp_reset_postdata();

		return array_merge( $featured_ids, $new_ids, $regular_ids );
	}

	private function render_all_services_markup(): string {
		$ordered_ids = $this->get_ordered_service_post_ids();
		if ( array() === $ordered_ids ) {
			return '';
		}

		$list_class = 'example-service-list example-service-list--our-services';

		ob_start();
		echo '<div class="' . esc_attr( $list_class ) . '">';
		foreach ( $ordered_ids as $post_id ) {
			echo $this->render_service_list_item_markup( (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * One footer service row (icon + title).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function render_footer_service_link_markup( int $post_id ): string {
		$slug = (string) get_post_field( 'post_name', $post_id );
		if ( 'help' === $slug ) {
			return '';
		}

		$title = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		$icon_markup = '';
		$list_icon_path = ExampleMediaBindings::service_list_icon_relative_path( $slug );
		if ( '' !== $list_icon_path ) {
			$icon_url = ContentMediaSync::get_media_url( $list_icon_path );
			if ( '' !== $icon_url ) {
				$icon_markup = '<img class="example-footer-service-icon" src="' . esc_url( $icon_url ) . '" alt="" width="384" height="256" loading="lazy" decoding="async">';
			}
		}

		return '<a class="example-footer-service-link" href="' . esc_url( $permalink ) . '">'
			. $icon_markup
			. '<span class="example-footer-service-title">' . esc_html( $title ) . '</span>'
			. '</a>';
	}

	/**
	 * Render one service as paragraph-like list item.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function render_service_list_item_markup( int $post_id ): string {
		$slug = (string) get_post_field( 'post_name', $post_id );
		if ( 'help' === $slug ) {
			return '';
		}

		$title = get_the_title( $post_id );
		$pill_markup = Front::featured_and_new_pill_markup( $post_id );
		$icon_markup = '';
		$list_icon_path = ExampleMediaBindings::service_list_icon_relative_path( $slug );
		if ( '' !== $list_icon_path ) {
			$icon_url = ContentMediaSync::get_media_url( $list_icon_path );
			if ( '' !== $icon_url ) {
				$icon_markup = '<img class="example-service-list-icon" src="' . esc_url( $icon_url ) . '" alt="" width="384" height="256" loading="lazy">';
			}
		}

		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		$pill_group_markup = '';
		if ( '' !== $pill_markup ) {
			$pill_group_markup = '<span class="example-service-list-pills">' . $pill_markup . '</span>';
		}

		return '<div class="example-service-list-item">'
			. '<a class="example-service-list-link" href="' . esc_url( $permalink ) . '">'
			. $icon_markup
			. $pill_group_markup
			. '<span class="example-service-list-title">' . esc_html( $title ) . '</span>'
			. '</a>'
			. '</div>';
	}

	/**
	 * @param string $url Full URL.
	 * @param string $label Link text.
	 *
	 * @return string
	 */
	private function render_service_view_all_row( string $url, string $label ): string {
		return '<div class="example-card-section-actions">'
			. '<a class="example-button-blue" href="' . esc_url( $url ) . '">'
			. esc_html( $label )
			. ' <i class="fas fa-arrow-right" aria-hidden="true"></i>'
			. '</a>'
			. '</div>';
	}
}
