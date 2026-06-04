<?php
/**
 * Blog Front Class
 *
 * Blog listings, archives, and single-post content filter. Shortcodes are registered in RegisterShortcodes.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleFront;

use ASC\AI_BOILER\ExampleCore\ArchiveConfig;
use ASC\AI_BOILER\ExampleCore\PostMeta;
use ASC\AI_BOILER\ExampleCore\CoreSettings;
use ASC\AI_BOILER\ExampleCore\ExamplePartialCatalog;
use WP_Query;

/**
 * Blog Front Class
 */
class BlogFront {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'filter_single_blog_content' ), 20 );
	}

	/**
	 * Home: latest blogs (max 6, test overrides).
	 *
	 * @return string
	 */
	public function shortcode_home_blogs(): string {
		$limit = ArchiveConfig::teaser_display_limit( ArchiveConfig::HOME_BLOG_LIMIT );
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			)
		);

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}

		ob_start();
		echo '<div class="example-card-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			echo $this->render_post_teaser( (int) get_the_ID(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		echo $this->render_view_all_row(
			ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_BLOG ),
			esc_html__( 'View All Blogs', \ASC_AI_BOILER_TEXT_DOMAIN )
		);
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Paged archive of all blog posts (blog page).
	 *
	 * @return string
	 */
	public function shortcode_all_blogs(): string {
		$page_id = (int) get_queried_object_id();
		if ( $page_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $page_id );
		if ( ! is_string( $permalink ) ) {
			return '';
		}

		$per_page = ArchiveConfig::archive_per_page();
		$paged = ArchivePagination::get_current_paged();

		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			)
		);

		ob_start();
		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			echo '<p class="example-archive-empty">' . esc_html__( 'No posts found.', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</p>';
		} else {
			$max_pages = max( 1, (int) $query->max_num_pages );

			echo '<div class="example-card-grid">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo $this->render_post_teaser( (int) get_the_ID(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			if ( $max_pages > 1 ) {
				echo ArchivePagination::render( $paged, $max_pages, $permalink ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			wp_reset_postdata();
		}

		$inner = (string) ob_get_clean();

		return Front::render_page_listing_archive_card_section_shell( $page_id, $inner );
	}

	private function render_view_all_row( string $url, string $label ): string {
		return '<div class="example-card-section-actions">'
			. '<a class="example-button-blue" href="' . esc_url( $url ) . '">'
			. esc_html( $label )
			. ' <i class="fas fa-arrow-right" aria-hidden="true"></i>'
			. '</a>'
			. '</div>';
	}
	/**
	 * Filter single blog content into custom markup.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public function filter_single_blog_content( string $content ): string {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return $content;
		}

		$date = get_the_date( '', $post_id );
		$tag_markup = Front::get_pill_markup( $post_id );
		$media_markup = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$media_markup = (string) get_the_post_thumbnail( $post_id, 'large', array( 'loading' => 'lazy' ) );
		} else {
			$media_markup = '<img src="' . esc_url( Front::media_url_for_post( $post_id, CoreSettings::SETTING_IMAGE_BLOG_DEFAULT ) ) . '" alt="' . esc_attr( CoreSettings::get_image_alt( CoreSettings::SETTING_IMAGE_BLOG_DEFAULT, (string) get_the_title( $post_id ) ) ) . '" width="1440" height="1080">';
		}

		$boiler_markup = Front::get_boiler_section_markup( ExamplePartialCatalog::KEY_BLOG_BOILER );
		$heading_markup = $tag_markup
			. '<h1 class="example-post-entry-title">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
		$meta_markup = '<p class="example-post-entry-date">' . esc_html( $date ) . '</p>';
		$main_markup = $content
			. $this->get_single_post_cta_link_markup( $post_id )
			. $this->render_view_all_row(
				ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_BLOG ),
				esc_html__( 'View All Blogs', \ASC_AI_BOILER_TEXT_DOMAIN )
			);

		return Front::render_single_entry_article_markup(
			$media_markup,
			$heading_markup,
			$meta_markup,
			$main_markup,
			$boiler_markup
		);
	}

	/**
	 * Optional blue CTA from Blog Settings → Link (label + URL meta).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_single_post_cta_link_markup( int $post_id ): string {
		$label = trim( (string) get_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_LABEL_META_KEY, true ) );
		$url_raw = trim( (string) get_post_meta( $post_id, PostMeta::BLOG_CTA_LINK_URL_META_KEY, true ) );
		if ( '' === $label || '' === $url_raw ) {
			return '';
		}

		$url = esc_url( $url_raw );
		if ( '' === $url ) {
			return '';
		}

		return $this->render_view_all_row( $url, $label );
	}

	/**
	 * Render a single blog post card.
	 *
	 * @param int $post_id Post ID.
	 * @param bool $show_tags Whether to show tag pills.
	 *
	 * @return string
	 */
	private function render_post_teaser( int $post_id, bool $show_tags ): string {
		$title = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$media_markup = get_the_post_thumbnail( $post_id, 'large', array( 'loading' => 'lazy' ) );
		} else {
			$media_markup = '<img src="' . esc_url( Front::media_url_for_post( $post_id, CoreSettings::SETTING_IMAGE_BLOG_DEFAULT ) ) . '" alt="' . esc_attr( CoreSettings::get_image_alt( CoreSettings::SETTING_IMAGE_BLOG_DEFAULT, $title ) ) . '" width="1440" height="1080">';
		}

		$date_markup = '<p class="example-post-entry-date">' . esc_html( get_the_date( '', $post_id ) ) . '</p>';
		$tag_markup = '';
		if ( $show_tags ) {
			$tag_markup = Front::get_pill_markup( $post_id );
		}

		return '<article class="example-card example-blog-card">'
			. '<div class="example-card-body">'
			. '<a class="example-card-media" href="' . esc_url( $permalink ) . '" tabindex="-1">' . $media_markup . '</a>'
			. '<div class="example-card-content example-card--light">'
			. $tag_markup
			. '<h3 class="example-card-title"><a href="' . esc_url( $permalink ) . '" tabindex="-1">' . esc_html( $title ) . '</a></h3>'
			. $date_markup
			. '<div class="example-card-cta">' . Front::read_more_button_html( $permalink ) . '</div>'
			. '</div>'
			. '</div>'
			. '</article>';
	}
}
