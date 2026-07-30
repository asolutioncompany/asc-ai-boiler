<?php
/**
 * Front Class
 *
 * Handles front-end initialization and enqueues front assets.
 *
 * @package asc-ai-example
 * @since 1.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\Media;
use ASC\AI_EXAMPLE\Core\PartialStore;
use ASC\AI_EXAMPLE\Core\ThemeShell;
use ASC\AI_EXAMPLE\Core\ArchiveConfig;
use ASC\AI_EXAMPLE\Core\CoreSettings;
use ASC\AI_EXAMPLE\Core\PartialCatalog;

use ASC\AI_EXAMPLE\Core\RegisterPortfolio;

/**
 * Front Class
 */
class Front {
	private const FRONT_EXCERPT_WORD_COUNT = 30;

	/**
	 * Initialize the Front class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize front components.
	 *
	 * @return void
	 */
	private function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ), 100 );
		add_action( 'wp_head', array( $this, 'render_favicon' ) );
		add_action( 'wp_footer', array( $this, 'render_scroll_top' ) );
		add_filter( 'excerpt_length', array( $this, 'get_front_excerpt_length' ), 999 );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );

		$site_front = new SiteFront();
		$call_to_action = new CallToAction();
		$blog_front = new BlogFront();
		$portfolio_front = new PortfolioFront();

		new SearchFront();
		new RegisterShortcodes( $site_front, $call_to_action, $blog_front, $portfolio_front );
	}

	/**
	 * Set shorter automatic excerpt length on front-end.
	 *
	 * @param int $length Default excerpt length (in words).
	 *
	 * @return int Excerpt length in words.
	 */
	public function get_front_excerpt_length( int $length ): int {
		if ( is_admin() ) {
			return $length;
		}

		return self::FRONT_EXCERPT_WORD_COUNT;
	}

	/**
	 * Enqueue front assets (front-end CSS and JavaScript).
	 *
	 * @return void
	 */
	public function enqueue_front_assets(): void {
		$plugin_url = plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE );
		$plugin_path = plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE );

		$css_file = 'assets/front/front.css';
		$js_file = 'assets/front/front.js';

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'example_site_front',
			$plugin_url . $css_file,
			array( 'dashicons' ),
			filemtime( $plugin_path . $css_file )
		);

		wp_enqueue_script(
			'example_site_front',
			$plugin_url . $js_file,
			array( 'jquery' ),
			filemtime( $plugin_path . $js_file ),
			true
		);

		wp_localize_script(
			'example_site_front',
			'example_site_front',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Add asc-site-dark or asc-site-light to the body class list based on the theme cookie.
	 *
	 * @param string[] $classes Existing body classes from WordPress.
	 * @return string[]
	 */
	public function filter_body_class( array $classes ): array {
		$classes[] = 'asc-site-light';
		return $classes;
	}

	/**
	 * Absolute URL for a path under the site home.
	 *
	 * @param string $path Path beginning with /.
	 *
	 * @return string
	 */
	public static function home_url_for_path( string $path ): string {
		if ( '' === $path ) {
			return esc_url( home_url( '/' ) );
		}
		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		return esc_url( home_url( $path ) );
	}

	/**
	 * Default image URL from a settings key.
	 *
	 * @param string $setting_key Image setting key.
	 *
	 * @return string
	 */
	public static function default_image_url_by_setting_key( string $setting_key ): string {
		return CoreSettings::get_image_url( $setting_key );
	}

	/**
	 * Default image alt from a settings key.
	 *
	 * @param string $setting_key Image setting key.
	 * @param string $fallback_alt Fallback alt text.
	 *
	 * @return string
	 */
	public static function default_image_alt_by_setting_key( string $setting_key, string $fallback_alt ): string {
		return CoreSettings::get_image_alt( $setting_key, $fallback_alt );
	}

	/**
	 * Effective image URL for a post: featured attachment, plugin media file, then settings default.
	 *
	 * @param int $post_id Post ID.
	 * @param string $setting_key Settings image key fallback.
	 *
	 * @return string
	 */
	public static function media_url_for_post( int $post_id, string $setting_key ): string {
		if ( has_post_thumbnail( $post_id ) ) {
			$url = wp_get_attachment_image_url( (int) get_post_thumbnail_id( $post_id ), 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return esc_url( $url );
			}
		}

		$slug = (string) get_post_field( 'post_name', $post_id );
		$post_type = (string) get_post_field( 'post_type', $post_id );
		if ( '' !== $slug && '' !== $post_type ) {
			$plugin_url = Media::get_post_media_url( $post_type, $slug );
			if ( '' !== $plugin_url ) {
				return $plugin_url;
			}
		}

		return self::default_image_url_by_setting_key( $setting_key );
	}

	/**
	 * Render a boiler partial wrapped in a section with a leading divider.
	 *
	 * @param string $partial_key One of PartialCatalog::KEY_AGENCY_BOILER or KEY_BLOG_BOILER.
	 *
	 * @return string
	 */
	public static function get_boiler_section_markup( string $partial_key ): string {
		$markup = PartialStore::get_raw_markup( $partial_key );
		if ( '' === trim( $markup ) ) {
			return '';
		}

		return self::wrap_divided_section_markup( wpautop( do_shortcode( $markup ) ) );
	}

	/**
	 * Wrap HTML in a section with a leading boiler divider above it.
	 *
	 * @param string $inner_html Processed partial or boiler body HTML.
	 *
	 * @return string
	 */
	public static function wrap_divided_section_markup( string $inner_html ): string {
		if ( '' === trim( $inner_html ) ) {
			return '';
		}

		return '<div class="example-boiler-section">'
			. '<hr class="example-boiler-divider" aria-hidden="true">'
			. $inner_html
			. '</div>';
	}

	/**
	 * Single blog layout: left 1/3 (featured image, boiler) | right 2/3 (tag, title, meta, content).
	 *
	 * @param string $media_markup Featured image HTML.
	 * @param string $heading_markup Pills, tags, and title markup.
	 * @param string $meta_markup Post meta (e.g. date); may be empty.
	 * @param string $main_markup Post content and trailing actions.
	 * @param string $sidebar_markup Boiler partial for the left column below the image.
	 *
	 * @return string
	 */
	public static function render_single_entry_article_markup(
		string $media_markup,
		string $heading_markup,
		string $meta_markup,
		string $main_markup,
		string $sidebar_markup
	): string {
		$meta_block_markup = '';
		if ( '' !== trim( $meta_markup ) ) {
			$meta_block_markup = '<div class="example-single-entry-meta">' . $meta_markup . '</div>';
		}

		$sidebar_block_markup = '';
		if ( '' !== trim( $sidebar_markup ) ) {
			$sidebar_block_markup = '<aside class="example-single-entry-sidebar" aria-label="'
				. esc_attr__( 'Related', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
				. '">'
				. $sidebar_markup
				. '</aside>';
		}

		return '<article class="example-single-entry">'
			. '<div class="example-single-entry-layout">'
			. '<div class="example-single-entry-column example-single-entry-column--left">'
			. '<div class="example-single-entry-column example-single-entry-column--lead">'
			. '<figure class="example-single-entry-media">' . $media_markup . '</figure>'
			. '</div>'
			. $sidebar_block_markup
			. '</div>'
			. '<div class="example-single-entry-column example-single-entry-column--main">'
			. '<header class="example-single-entry-header">'
			. '<div class="example-single-entry-heading">' . $heading_markup . '</div>'
			. $meta_block_markup
			. '</header>'
			. '<div class="example-single-entry-content">'
			. '<div class="example-post-entry-body">' . $main_markup . '</div>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</article>';
	}

	/**
	 * Wrap paged listing archive grid and pagination in example-card-section (no hero column).
	 *
	 * @param int $page_id Current WordPress page ID.
	 * @param string $inner_markup Grid and pagination HTML.
	 *
	 * @return string
	 */
	public static function render_page_listing_archive_card_section_shell( int $page_id, string $inner_markup ): string {
		if ( $page_id <= 0 ) {
			return $inner_markup;
		}

		return '<div class="example-section example-card-section example-archive-listing-card-section">'
			. '<div class="example-card-section-content">'
			. $inner_markup
			. '</div>'
			. '</div>';
	}

	/**
	 * Build pills markup for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function get_pill_markup( int $post_id ): string {
		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) ) {
			$post_type = '';
		}

		$primary_post_type_tag = self::get_primary_post_type_tag( $post_type );
		if ( empty( $primary_post_type_tag['href'] ) || empty( $primary_post_type_tag['label'] ) ) {
			return '';
		}

		return '<div class="example-tags-wrapper">'
			. '<a class="example-tag" href="' . esc_url( $primary_post_type_tag['href'] ) . '">' . esc_html( $primary_post_type_tag['label'] ) . '</a>'
			. '</div>';
	}

	/**
	 * First paragraph HTML from post content (after typical the_content filters).
	 *
	 * @param string $content_raw Raw post_content.
	 *
	 * @return string Single paragraph markup, or empty when there is no text.
	 */
	public static function first_paragraph_html_from_post_content( string $content_raw ): string {
		$content_raw = trim( $content_raw );
		if ( '' === $content_raw ) {
			return '';
		}

		$html = ThemeShell::apply_post_content_filters( $content_raw );
		if ( ! is_string( $html ) ) {
			$html = '';
		}

		if ( preg_match( '/<p\b[^>]*>.*?<\/p>/is', $html, $matches ) ) {
			return $matches[0];
		}

		$text = trim( wp_strip_all_tags( $html ) );
		if ( '' === $text ) {
			return '';
		}

		return '<p>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Read More button linking to a single post.
	 *
	 * @param string $permalink Post permalink.
	 *
	 * @return string
	 */
	public static function read_more_button_html( string $permalink ): string {
		if ( '' === $permalink ) {
			return '';
		}

		return '<a class="example-button-blue" href="' . esc_url( $permalink ) . '">'
			. esc_html__( 'Read More', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
			. ' →</a>';
	}

	/**
	 * Leading crumb link before taxonomy tags.
	 *
	 * @param string $post_type Post type key.
	 *
	 * @return array{label:string, href:string}
	 */
	private static function get_primary_post_type_tag( string $post_type ): array {
		if ( RegisterPortfolio::POST_TYPE === $post_type ) {
			return array(
				'label' => __( 'Portfolio', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
				'href' => ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_PORTFOLIO ),
			);
		}

		if ( 'post' === $post_type ) {
			return array(
				'label' => __( 'Blog', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
				'href' => ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_BLOG ),
			);
		}

		return array(
			'label' => '',
			'href' => '',
		);
	}

	public function render_scroll_top(): void {
		echo '<button type="button" class="asc-scroll-top" aria-label="' . esc_attr__( 'Scroll to top', 'asc-ai-boiler' ) . '"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>';
	}

	/**
	 * Output the SVG favicon in the header.
	 *
	 * @return void
	 */
	public function render_favicon(): void {
		$url = plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE ) . 'content/other-media/performance.svg';
		echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $url ) . '">' . "\n";
	}
}
