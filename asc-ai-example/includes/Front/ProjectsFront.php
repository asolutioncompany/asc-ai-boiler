<?php
/**
 * Projects Front Class
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

use ASC\AI_EXAMPLE\Core\ArchiveConfig;
use ASC\AI_EXAMPLE\Core\CoreSettings;
use ASC\AI_EXAMPLE\Core\PostMeta;
use ASC\AI_EXAMPLE\Core\RegisterProjects;
use WP_Query;

/**
 * Projects Front Class
 */
class ProjectsFront {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'filter_single_project_content' ), 20 );
	}

	/**
	 * Filter single project content into custom markup.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public function filter_single_project_content( string $content ): string {
		if ( is_admin() || ! is_singular( RegisterProjects::POST_TYPE ) ) {
			return $content;
		}

		$request_path = wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! is_string( $request_path ) || 1 !== preg_match( '#^/projects/[^/]+/?$#', $request_path ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return $content;
		}

		$tag_markup = Front::get_pill_markup( $post_id );
		$media_markup = Front::single_page_media_markup_with_setting_key( $post_id, CoreSettings::SETTING_IMAGE_PROJECTS );
		$gallery_markup = $this->render_project_gallery_markup( $post_id );
		$boiler_markup = Front::get_agency_boiler_markup();
		$heading_markup = $tag_markup
			. '<h1 class="example-post-entry-title">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
		$main_markup = $content
			. $gallery_markup
			. $this->render_project_view_all_row(
				ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_PROJECTS ),
				esc_html__( 'View All Projects', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
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
	 * Render the additional photos mosaic for a single project (returns empty string when no photos saved).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function render_project_gallery_markup( int $post_id ): string {
		$attachment_ids = PostMeta::parse_project_gallery_attachment_ids(
			get_post_meta( $post_id, PostMeta::PROJECT_GALLERY_META_KEY, true )
		);
		if ( empty( $attachment_ids ) ) {
			return '';
		}

		$total_photos = count( $attachment_ids );
		$has_odd_remainder = false;
		if ( 1 === ( $total_photos % 2 ) ) {
			$has_odd_remainder = true;
		}

		$last_index = $total_photos - 1;

		$tiles_html = '';
		foreach ( $attachment_ids as $index => $attachment_id ) {
			$image_html = wp_get_attachment_image(
				$attachment_id,
				'large',
				false,
				array(
					'class' => 'example-project-gallery-image',
					'loading' => 'lazy',
				)
			);
			if ( ! is_string( $image_html ) || '' === $image_html ) {
				continue;
			}

			$tile_classes = 'example-project-gallery-tile';
			if ( $has_odd_remainder && $index === $last_index ) {
				$tile_classes .= ' example-project-gallery-tile--full';
			}

			$tiles_html .= '<div class="' . esc_attr( $tile_classes ) . '">' . $image_html . '</div>';
		}

		if ( '' === $tiles_html ) {
			return '';
		}

		return '<div class="example-project-gallery">' . $tiles_html . '</div>';
	}


	/**
	 * @return string
	 */
	public function shortcode_all_projects(): string {
		return $this->render_all_projects_archive();
	}

	/**
	 * Paged archive of all projects (featured work page).
	 *
	 * @return string
	 */
	private function render_all_projects_archive(): string {
		$page_id = (int) get_queried_object_id();
		if ( $page_id <= 0 ) {
			return '';
		}

		$query = new WP_Query(
			array(
				'post_type'              => RegisterProjects::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		ob_start();
		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			echo '<p class="example-archive-empty">' . esc_html__( 'No featured work found.', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '</p>';
		} else {
			echo '<div class="example-card-grid">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo $this->render_project_card_markup( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			wp_reset_postdata();
		}

		$inner = (string) ob_get_clean();

		return Front::render_page_listing_archive_card_section_shell( $page_id, $inner );
	}


	/**
	 * @param string $url Full URL.
	 * @param string $label Link text.
	 *
	 * @return string
	 */
	private function render_project_view_all_row( string $url, string $label ): string {
		return '<div class="example-card-section-actions">'
			. '<a class="example-button-blue" href="' . esc_url( $url ) . '">'
			. esc_html( $label )
			. ' →'
			. '</a>'
			. '</div>';
	}

	/**
	 * Render a single project card for projects page sections.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function render_project_card_markup( int $post_id ): string {
		$title = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$media_markup = get_the_post_thumbnail( $post_id, 'large', array( 'loading' => 'lazy' ) );
		} else {
			$url = Front::media_url_for_post( $post_id, CoreSettings::SETTING_IMAGE_PROJECTS );
			$alt = Front::default_image_alt_by_setting_key( CoreSettings::SETTING_IMAGE_PROJECTS, $title );
			$media_markup = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" width="1440" height="1080">';
		}

		$tags_markup = Front::get_pill_markup( $post_id );

		return '<article class="example-card example-project-card">'
			. '<div class="example-card-body">'
			. '<a class="example-card-media" href="' . esc_url( $permalink ) . '" tabindex="-1">' . $media_markup . '</a>'
			. '<div class="example-card-content example-card--light">'
			. $tags_markup
			. '<h3 class="example-card-title"><a href="' . esc_url( $permalink ) . '" tabindex="-1">' . esc_html( $title ) . '</a></h3>'
			. '<div class="example-card-cta">' . Front::read_more_button_html( $permalink ) . '</div>'
			. '</div>'
			. '</div>'
			. '</article>';
	}
}
