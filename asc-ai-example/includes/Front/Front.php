<?php
/**
 * Front Class
 *
 * Handles front-end initialization and enqueues front assets.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

use ASC\AI_BOILER\Core\Media;
use ASC\AI_BOILER\Core\PartialStore;
use ASC\AI_BOILER\Core\ThemeShell;
use ASC\AI_EXAMPLE\Core\ArchiveConfig;
use ASC\AI_EXAMPLE\Core\CoreSettings;
use ASC\AI_EXAMPLE\Core\RegisterProjects;
use ASC\AI_EXAMPLE\Core\RegisterServices;
use ASC\AI_EXAMPLE\Core\PartialCatalog;

/**
 * Front Class
 */
class Front {
	private const FRONT_EXCERPT_WORD_COUNT = 30
	;

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
		$projects_front = new ProjectsFront();
		$services_front = new ServicesFront();
		new SearchFront();
		new RegisterShortcodes( $site_front, $call_to_action, $blog_front, $projects_front, $services_front );
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
				'ajax_nonce' => wp_create_nonce( 'asc-ai-boiler-front-ajax-nonce' ),
			)
		);
	}

	/**
	 * Add asc-site-dark or asc-site-light to the body class list based on the theme cookie.
	 *
	 * Hooked to 'body_class'. PHP reads the cookie set by the client so the correct
	 * class is present in the cached HTML, eliminating JS flash-of-wrong-theme.
	 * Default: light mode (asc-light).
	 *
	 * Caching Notes:
	 * Page caching solutions (e.g. Varnish, Nginx FastCGI cache, WP Super Cache, Cloudflare)
	 * MUST be configured to cache both theme versions separately by varying on the cookie:
	 * Cookie Name: 'asc_cookie'
	 * Settings / Values: 'asc-light' and 'asc-dark'.
	 *
	 * @param string[] $classes Existing body classes from WordPress.
	 * @return string[]
	 */
	public function filter_body_class( array $classes ): array {
		$raw = (string) ( $_COOKIE['asc_cookie'] ?? $_COOKIE['asc-cookie'] ?? '' );
		$theme_class = 'asc-site-light'; // Default to light mode for this website
		if ( 'asc-dark' === $raw ) {
			$theme_class = 'asc-site-dark';
		}
		$classes[] = $theme_class;
		return $classes;
	}

	/**
	 * Absolute URL for a path under the site home (e.g. /wp-content/uploads/...).
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
	 * Featured / New label spans for services and projects CPTs.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */


	/**
	 * Single CPT page media column with settings-based image default.
	 *
	 * @param int $post_id Post ID.
	 * @param string $setting_key Image setting key.
	 *
	 * @return string
	 */
	public static function single_page_media_markup_with_setting_key( int $post_id, string $setting_key ): string {
		if ( has_post_thumbnail( $post_id ) ) {
			return (string) get_the_post_thumbnail( $post_id, 'large', array( 'loading' => 'lazy' ) );
		}

		$title = (string) get_the_title( $post_id );
		$default_image = self::media_url_for_post( $post_id, $setting_key );
		$default_alt = self::default_image_alt_by_setting_key( $setting_key, $title );

		return '<img src="' . esc_url( $default_image ) . '" alt="' . esc_attr( $default_alt ) . '" width="1440" height="1080">';
	}

	/**
	 * Agency boiler markup appended to single service and project posts.
	 *
	 * @return string
	 */
	public static function get_agency_boiler_markup(): string {
		return self::get_boiler_section_markup( PartialCatalog::KEY_AGENCY_BOILER );
	}

	/**
	 * Agency boiler markup for single service posts (purple divider).
	 *
	 * @return string
	 */
	public static function get_service_boiler_markup(): string {
		return self::get_boiler_section_markup( PartialCatalog::KEY_AGENCY_BOILER );
	}

	/**
	 * Render a boiler partial wrapped in a section with a leading divider.
	 *
	 * Looks up the named partial in the Partials CPT. Returns an empty string when the post is missing or has no
	 * body. Used by the single service / project / blog auto-append and
	 * by the [example_boiler_agency] and [example_blog_boiler] shortcodes.
	 *
	 * The partial body is run through `wpautop()` so plain-text boiler sources are wrapped in paragraph
	 * tags automatically.
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
	 * Single blog / project layout: left 1/3 (featured image, boiler) | right 2/3 (tag, title, meta, content).
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
			. '<div class="example-single-entry-column example-single-entry-column--lead">'
			. '<figure class="example-single-entry-media">' . $media_markup . '</figure>'
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
			. $sidebar_block_markup
			. '</div>'
			. '</article>';
	}

	/**
	 * Wrap paged listing archive grid and pagination in example-card-section (no hero column).
	 * Page h1 titles belong in page content, not here.
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
	 * First paragraph HTML from post content (after {@see the_content} filters).
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
	 * Read More button linking to a single post (listing cards and featured home blocks).
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
	 * Leading crumb link before taxonomy tags (services, projects, blog).
	 *
	 * @param string $post_type Post type key.
	 *
	 * @return array{label:string, href:string}
	 */
	private static function get_primary_post_type_tag( string $post_type ): array {
		if ( RegisterServices::POST_TYPE === $post_type ) {
			return array(
				'label' => 'Services',
				'href'  => ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_SERVICES ),
			);
		}

		if ( RegisterProjects::POST_TYPE === $post_type ) {
			return array(
				'label' => __( 'Projects', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
				'href'  => ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_PROJECTS ),
			);
		}

		if ( 'post' === $post_type ) {
			return array(
				'label' => __( 'Blog', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
				'href'  => ArchiveConfig::url_for_page_slug( ArchiveConfig::SLUG_BLOG ),
			);
		}

		return array(
			'label' => '',
			'href'  => '',
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
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><style>path { fill: #0b7285; } @media (prefers-color-scheme: dark) { path { fill: #67d8ef; } }</style><path d="M3.76 17.010h12.48c1.1-1.38 1.76-3.11 1.76-5.010 0-4.41-3.58-8-8-8s-8 3.59-8 8c0 1.9 0.66 3.63 1.76 5.010zM9 6c0-0.55 0.45-1 1-1s1 0.45 1 1c0 0.56-0.45 1-1 1s-1-0.44-1-1zM4 8c0-0.55 0.45-1 1-1s1 0.45 1 1c0 0.56-0.45 1-1 1s-1-0.44-1-1zM8.52 11.4c0.84-0.83 6.51-3.5 6.51-3.5s-2.66 5.68-3.49 6.51c-0.84 0.84-2.18 0.84-3.020 0-0.83-0.83-0.83-2.18 0-3.010zM3 13c0-0.55 0.45-1 1-1s1 0.45 1 1c0 0.56-0.45 1-1 1s-1-0.44-1-1zM9 13c0-0.55 0.45-1 1-1s1 0.45 1 1c0 0.56-0.45 1-1 1s-1-0.44-1-1zM15 13c0-0.55 0.45-1 1-1s1 0.45 1 1c0 0.56-0.45 1-1 1s-1-0.44-1-1z"/></svg>';
		echo '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,' . base64_encode( $svg ) . '">' . "\n";
	}

}
