<?php
/**
 * Services Front Class
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\ArchiveConfig;
use ASC\AI_EXAMPLE\Core\CoreSettings;
use ASC\AI_EXAMPLE\Core\MediaBindings;
use ASC\AI_EXAMPLE\Core\RegisterServices;
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

		$slug = (string) get_post_field( 'post_name', $post_id );
		$tag_markup = Front::get_pill_markup( $post_id );
		$media_markup = self::service_combined_icon_html( $slug, 'large' );
		$boiler_markup = Front::get_service_boiler_markup();
		$heading_markup = $tag_markup
			. '<h1 class="example-post-entry-title">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
		$main_markup = $content
			. $this->render_service_view_all_row(
				ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_SERVICES ),
				esc_html__( 'View All Services', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
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

		$ids = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = (int) get_the_ID();
			if ( 'help' !== (string) get_post_field( 'post_name', $post_id ) ) {
				$ids[] = $post_id;
			}
		}
		wp_reset_postdata();

		return $ids;
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
	 * Combined dashicon markup for a service at a given display size.
	 *
	 * @param string $slug Service post slug.
	 * @param string $size One of large | medium | small.
	 *
	 * @return string
	 */
	private static function service_combined_icon_html( string $slug, string $size ): string {
		$fg_icon = MediaBindings::service_dashicon( $slug );
		if ( '' === $fg_icon ) {
			return '';
		}

		return '<span class="example-service-icon example-service-icon--' . esc_attr( $size ) . '" aria-hidden="true">'
			. '<span class="dashicons ' . esc_attr( $fg_icon ) . ' example-service-icon-fg"></span>'
			. '<span class="dashicons dashicons-wordpress example-service-icon-wp"></span>'
			. '</span>';
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

		$title = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		$icon_markup = self::service_combined_icon_html( $slug, 'small' );

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

		$title = get_the_title( $post_id );
		$icon_markup = self::service_combined_icon_html( $slug, 'medium' );

		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		return '<div class="example-service-list-item">'
			. '<a class="example-service-list-link" href="' . esc_url( $permalink ) . '">'
			. $icon_markup
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
			. ' →'
			. '</a>'
			. '</div>';
	}
}
