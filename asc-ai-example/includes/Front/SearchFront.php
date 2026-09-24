<?php
/**
 * Search results, archive, and 404 rendering.
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\ThemeShell;
use ASC\AI_EXAMPLE\Core\ArchiveConfig;
use ASC\AI_EXAMPLE\Core\CoreSettings;
use ASC\AI_EXAMPLE\Core\RegisterPortfolio;

/**
 * @since 1.0
 * Handles search results, archive, and 404 page rendering.
 */
class SearchFront {

	public function __construct() {
		add_filter( ThemeShell::FILTER_MAIN, array( $this, 'filter_main_markup' ) );
		add_action( 'pre_get_posts', array( $this, 'set_posts_per_page' ) );
		add_action( 'template_redirect', array( $this, 'redirect_disabled_pages' ) );
	}

	public function redirect_disabled_pages(): void {
		$search_enabled = (bool) \ASC_AI_EXAMPLE_SEARCH_ENABLED;
		$archive_enabled = (bool) \ASC_AI_EXAMPLE_ARCHIVE_ENABLED;
		if ( is_tag( 'blog' ) ) {
			wp_safe_redirect( ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_BLOG ), 301 );
			exit;
		}
		if ( is_tag( 'portfolio' ) ) {
			wp_safe_redirect( ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_PORTFOLIO ), 301 );
			exit;
		}

		if ( ! $search_enabled && is_search() ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		if ( ! $archive_enabled && is_archive() ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	public function set_posts_per_page( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$search_enabled = (bool) \ASC_AI_EXAMPLE_SEARCH_ENABLED;
		$archive_enabled = (bool) \ASC_AI_EXAMPLE_ARCHIVE_ENABLED;

		if ( $search_enabled && $query->is_search() ) {
			$query->set( 'posts_per_page', ArchiveConfig::SEARCH_ARCHIVE_LIMIT );
		}

		if ( $archive_enabled && $query->is_archive() ) {
			$query->set( 'posts_per_page', ArchiveConfig::SEARCH_ARCHIVE_LIMIT );
			if ( $query->is_category() ) {
				$query->set( 'post_type', array( 'post', RegisterPortfolio::POST_TYPE ) );
			}
		}
	}

	/**
	 * @param mixed $markup Accumulated filter value.
	 *
	 * @return string|null String to override the main body; null to let the boiler stub run.
	 */
	public function filter_main_markup( mixed $markup ): ?string {
		if ( is_string( $markup ) ) {
			return $markup;
		}

		if ( is_404() ) {
			return $this->render_404();
		}

		if ( is_search() && (bool) \ASC_AI_EXAMPLE_SEARCH_ENABLED ) {
			return $this->render_search();
		}

		if ( is_archive() && (bool) \ASC_AI_EXAMPLE_ARCHIVE_ENABLED ) {
			return $this->render_archive();
		}

		return null;
	}

	private function render_404(): string {
		$page = get_page_by_path( 'page-not-found', OBJECT, 'page' );
		if ( ! ( $page instanceof \WP_Post ) || 'publish' !== $page->post_status ) {
			return '';
		}

		$raw = str_replace( '{home_url}', esc_url( home_url( '/' ) ), (string) $page->post_content );

		return ThemeShell::apply_post_content_filters( $raw );
	}

	private function render_search(): string {
		$heading_text = sprintf(
			/* translators: %s: search query */
			esc_html__( 'Search results for: %s', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			esc_html( get_search_query() )
		);
		$heading = '<h1 class="example-page-title">' . $heading_text . '</h1>';

		if ( ! have_posts() ) {
			return '<section class="example-full-content">'
				. $heading
				. '<div class="example-section example-card-section example-archive-listing-card-section">'
				. '<div class="example-card-section-content">'
				. '<p class="example-archive-empty">' . esc_html__( 'No results found.', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '</p>'
				. '</div>'
				. '</div>'
				. '</section>';
		}

		return $this->render_card_loop( $heading );
	}

	private function render_archive(): string {
		$heading = $this->render_archive_heading();
		$section_class = 'example-full-content';
		if ( is_category() ) {
			$section_class = 'example-taxonomy-archive-page';
		}

		if ( ! have_posts() ) {
			return '<section class="' . esc_attr( $section_class ) . '">'
				. $heading
				. '<div class="example-section example-card-section example-archive-listing-card-section">'
				. '<div class="example-card-section-content">'
				. '<p class="example-archive-empty">' . esc_html__( 'No posts found.', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '</p>'
				. '</div>'
				. '</div>'
				. '</section>';
		}

		return $this->render_card_loop( $heading, $section_class );
	}

	private function render_archive_heading(): string {
		if ( ! is_category() ) {
			return '<h1 class="example-page-title">' . wp_kses_post( get_the_archive_title() ) . '</h1>';
		}

		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return '<h1 class="example-page-title">' . wp_kses_post( get_the_archive_title() ) . '</h1>';
		}

		$archive_type = esc_html__( 'Blog Topic', \ASC_AI_EXAMPLE_TEXT_DOMAIN );
		$portfolio_items = get_posts(
			array(
				'post_type' => RegisterPortfolio::POST_TYPE,
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'cat' => (int) $term->term_id,
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $portfolio_items ) ) {
			$archive_type = esc_html__( 'Portfolio Category', \ASC_AI_EXAMPLE_TEXT_DOMAIN );
		}
		$archive_subtitle = esc_html__( 'Archive', \ASC_AI_EXAMPLE_TEXT_DOMAIN );

		$intro_markup = $this->render_taxonomy_description_shortcode();

		return '<header class="example-taxonomy-archive-header">'
			. '<div class="example-taxonomy-archive-header-content">'
			. '<div class="example-taxonomy-archive-heading">'
			. '<p class="example-taxonomy-archive-type">' . $archive_type . '</p>'
			. '<p class="example-taxonomy-archive-subtitle">' . $archive_subtitle . '</p>'
			. '</div>'
			. '<div class="example-taxonomy-archive-copy">'
			. '<h1 class="example-page-title">' . esc_html( $term->name ) . '</h1>'
			. $intro_markup
			. '</div>'
			. '</div>'
			. '</header>';
	}

	public function render_taxonomy_description_shortcode( array|string $attributes = array() ): string {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}
		$attributes = shortcode_atts(
			array(
				'taxonomy' => '',
				'slug' => '',
			),
			$attributes,
			'example_taxonomy_description'
		);

		$term = get_queried_object();
		$taxonomy = sanitize_key( (string) $attributes['taxonomy'] );
		$slug = sanitize_title( (string) $attributes['slug'] );
		if ( '' !== $taxonomy && '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
		}

		if ( ! $term instanceof \WP_Term ) {
			return '';
		}
		$description = trim( wp_kses_post( (string) $term->description ) );
		if ( '' === $description ) {
			return '';
		}
		$description = wpautop( $description );

		return '<div class="example-taxonomy-archive-intro">' . $description . '</div>';
	}

	private function render_card_loop( string $heading, string $section_class = 'example-full-content' ): string {
		global $wp_query;

		$paged = ArchivePagination::get_current_paged();
		$max_pages = max( 1, (int) $wp_query->max_num_pages );
		$base_url = get_pagenum_link( 1 );

		ob_start();

		echo '<section class="' . esc_attr( $section_class ) . '">';
		echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="example-section example-card-section example-archive-listing-card-section">';
		echo '<div class="example-card-section-content">';
		echo '<div class="example-card-grid">';

		while ( have_posts() ) {
			the_post();
			echo $this->render_post_card( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';

		if ( $max_pages > 1 ) {
			echo ArchivePagination::render( $paged, $max_pages, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';
		echo '</div>';
		echo '</section>';
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	private function render_post_card( int $post_id ): string {
		$title = (string) get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		$post_type = (string) get_post_type( $post_id );
		$card_class = 'example-blog-card';
		$image_setting = CoreSettings::SETTING_IMAGE_BLOG_DEFAULT;
		if ( RegisterPortfolio::POST_TYPE === $post_type ) {
			$card_class = 'example-project-card';
			$image_setting = CoreSettings::SETTING_IMAGE_PORTFOLIO;
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$media_markup = (string) get_the_post_thumbnail(
				$post_id,
				'large',
				array(
					'loading' => 'lazy',
					'sizes' => Front::CARD_IMAGE_SIZES,
				)
			);
		} else {
			$image_url = Front::media_url_for_post( $post_id, $image_setting );
			$image_alt = Front::default_image_alt_by_setting_key( $image_setting, $title );
			$media_markup = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $image_alt ) . '" width="1440" height="1080">';
		}

		$date_markup = '<p class="example-post-entry-date">' . esc_html( (string) get_the_date( '', $post_id ) ) . '</p>';
		$tags_markup = Front::get_pill_markup( $post_id );

		return '<article class="example-card ' . esc_attr( $card_class ) . '">'
			. '<div class="example-card-body">'
			. '<a class="example-card-media" href="' . esc_url( $permalink ) . '" tabindex="-1">' . $media_markup . '</a>'
			. '<div class="example-card-content example-card--light">'
			. $tags_markup
			. '<h2 class="example-card-title"><a href="' . esc_url( $permalink ) . '" tabindex="-1">' . esc_html( $title ) . '</a></h2>'
			. $date_markup
			. '<div class="example-card-cta">' . Front::read_more_button_html( $permalink, $title ) . '</div>'
			. '</div>'
			. '</div>'
			. '</article>';
	}
}
