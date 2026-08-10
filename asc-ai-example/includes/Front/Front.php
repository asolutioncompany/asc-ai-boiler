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
use ASC\AI_EXAMPLE\Core\PostMeta;
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
		add_action( 'after_setup_theme', array( self::class, 'register_social_image_size' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ), 100 );
		add_action( 'wp_head', array( $this, 'render_favicon' ) );
		add_action( 'wp_head', array( $this, 'render_social_image_meta' ), 5 );
		add_action( 'wpseo_add_opengraph_images', array( $this, 'filter_yoast_opengraph_container' ), 10, 1 );
		add_filter( 'wpseo_opengraph_image', array( $this, 'filter_yoast_og_image' ), 10, 1 );
		add_filter( 'wpseo_opengraph_image_width', array( $this, 'filter_yoast_og_image_width' ), 10, 1 );
		add_filter( 'wpseo_opengraph_image_height', array( $this, 'filter_yoast_og_image_height' ), 10, 1 );
		add_filter( 'wpseo_opengraph_image_type', array( $this, 'filter_yoast_og_image_type' ), 10, 1 );
		add_filter( 'wpseo_og_image_width', array( $this, 'filter_yoast_og_image_width' ), 10, 1 );
		add_filter( 'wpseo_og_image_height', array( $this, 'filter_yoast_og_image_height' ), 10, 1 );
		add_filter( 'wpseo_og_image_type', array( $this, 'filter_yoast_og_image_type' ), 10, 1 );
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
			$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
			if ( function_exists( 'wp_get_original_image_url' ) ) {
				$url = wp_get_original_image_url( $thumbnail_id );
				if ( is_string( $url ) && '' !== $url ) {
					return esc_url( $url );
				}
			}

			$url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
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

		$featured_markup = '';
		$is_featured = '1' === (string) get_post_meta( $post_id, PostMeta::FEATURED_META_KEY, true );
		if ( $is_featured ) {
			$featured_markup = '<div class="example-featured-badge">'
				. esc_html__( 'FEATURED', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
				. '</div>';
		}

		return $featured_markup
			. '<div class="example-tags-wrapper">'
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

	/**
	 * Register custom image size for Open Graph / social media banner cards (1.91:1 aspect ratio).
	 *
	 * @return void
	 */
	public static function register_social_image_size(): void {
		add_image_size( 'asc_social_og', 1200, 627, true );
	}

	/**
	 * Get setting image key for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private static function get_setting_key_for_post_type( string $post_type ): string {
		if ( RegisterPortfolio::POST_TYPE === $post_type ) {
			return CoreSettings::SETTING_IMAGE_PORTFOLIO;
		}

		return CoreSettings::SETTING_IMAGE_BLOG_DEFAULT;
	}

	/**
	 * Build complete social image metadata array (url, width, height, mime type, attachment ID).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{url:string, width:int, height:int, type:string, id:int}|null
	 */
	public static function get_social_image_data( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}

		$post_type = (string) get_post_field( 'post_type', $post_id );
		$setting_key = self::get_setting_key_for_post_type( $post_type );

		$attachment_id = 0;
		$url = '';
		$width = 0;
		$height = 0;
		$type = '';

		if ( has_post_thumbnail( $post_id ) ) {
			$attachment_id = (int) get_post_thumbnail_id( $post_id );
			if ( $attachment_id > 0 ) {
				$src = wp_get_attachment_image_src( $attachment_id, 'asc_social_og' );
				if ( is_array( $src ) && ! empty( $src[0] ) ) {
					$url    = (string) $src[0];
					$width  = (int) $src[1];
					$height = (int) $src[2];
				} elseif ( function_exists( 'wp_get_original_image_url' ) ) {
					$orig_url = wp_get_original_image_url( $attachment_id );
					if ( is_string( $orig_url ) && '' !== $orig_url ) {
						$url = $orig_url;
					}
				}
			}
		}

		if ( '' === $url ) {
			$url = self::media_url_for_post( $post_id, $setting_key );
			if ( '' !== $url && function_exists( 'attachment_url_to_postid' ) ) {
				$att_id = (int) attachment_url_to_postid( $url );
				if ( $att_id > 0 ) {
					$attachment_id = $att_id;
					$src = wp_get_attachment_image_src( $attachment_id, 'asc_social_og' );
					if ( is_array( $src ) && ! empty( $src[0] ) ) {
						$width  = (int) $src[1];
						$height = (int) $src[2];
					}
				}
			}
		}

		if ( '' === $url ) {
			return null;
		}

		if ( $attachment_id > 0 ) {
			if ( $width <= 0 || $height <= 0 ) {
				$meta = wp_get_attachment_metadata( $attachment_id );
				if ( is_array( $meta ) && ! empty( $meta['sizes']['asc_social_og'] ) ) {
					$width  = (int) $meta['sizes']['asc_social_og']['width'];
					$height = (int) $meta['sizes']['asc_social_og']['height'];
				} elseif ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
					$width  = (int) $meta['width'];
					$height = (int) $meta['height'];
				}
			}

			$mime = get_post_mime_type( $attachment_id );
			if ( is_string( $mime ) && '' !== $mime ) {
				$type = $mime;
			}
		}

		if ( '' === $type ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( is_string( $path ) ) {
				$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( 'jpg' === $ext || 'jpeg' === $ext ) {
					$type = 'image/jpeg';
				} elseif ( 'png' === $ext ) {
					$type = 'image/png';
				} elseif ( 'webp' === $ext ) {
					$type = 'image/webp';
				} elseif ( 'gif' === $ext ) {
					$type = 'image/gif';
				}
			}
		}

		if ( '' === $type ) {
			$type = 'image/jpeg';
		}

		return array(
			'url'    => esc_url( $url ),
			'width'  => $width,
			'height' => $height,
			'type'   => $type,
			'id'     => $attachment_id,
		);
	}

	/**
	 * Effective social image URL for singular post/CPT.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function get_social_image_url( int $post_id ): string {
		$data = self::get_social_image_data( $post_id );
		return is_array( $data ) ? $data['url'] : '';
	}

	/**
	 * Get width and height dimensions of post's featured image attachment.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{width:int, height:int}|null
	 */
	public static function get_social_image_dimensions( int $post_id ): ?array {
		$data = self::get_social_image_data( $post_id );
		if ( is_array( $data ) && $data['width'] > 0 && $data['height'] > 0 ) {
			return array(
				'width'  => $data['width'],
				'height' => $data['height'],
			);
		}
		return null;
	}

	/**
	 * Output <meta name="image" property="og:image" content="..."> on wp_head.
	 *
	 * @return void
	 */
	public function render_social_image_meta(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$image_data = self::get_social_image_data( (int) $post_id );
		if ( is_array( $image_data ) && ! empty( $image_data['url'] ) ) {
			echo '<meta name="image" property="og:image" content="' . esc_url( $image_data['url'] ) . '">' . "\n";
		}
	}

	/**
	 * Force Yoast SEO's OpenGraph image container to use the complete social image array.
	 *
	 * @param object $image_container Yoast SEO OpenGraph image container instance.
	 *
	 * @return void
	 */
	public function filter_yoast_opengraph_container( $image_container ): void {
		if ( ! is_singular() || ! is_object( $image_container ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$image_data = self::get_social_image_data( (int) $post_id );
		if ( is_array( $image_data ) && method_exists( $image_container, 'clear' ) && method_exists( $image_container, 'add_image' ) ) {
			$image_container->clear();
			$image_container->add_image( array(
				'url'    => $image_data['url'],
				'width'  => $image_data['width'],
				'height' => $image_data['height'],
				'type'   => $image_data['type'],
				'id'     => $image_data['id'],
			) );
		}
	}

	/**
	 * Filter Yoast SEO og:image URL on singular post/CPT pages.
	 *
	 * @param string $img_url Default image URL set by Yoast.
	 *
	 * @return string
	 */
	public function filter_yoast_og_image( string $img_url ): string {
		if ( ! is_singular() ) {
			return $img_url;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $img_url;
		}

		$social_url = self::get_social_image_url( (int) $post_id );
		return '' !== $social_url ? $social_url : $img_url;
	}

	/**
	 * Filter Yoast SEO og:image:width on singular post/CPT pages.
	 *
	 * @param mixed $width Default width set by Yoast.
	 *
	 * @return mixed
	 */
	public function filter_yoast_og_image_width( $width ) {
		if ( ! is_singular() ) {
			return $width;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $width;
		}

		$dims = self::get_social_image_dimensions( (int) $post_id );
		return null !== $dims ? $dims['width'] : $width;
	}

	/**
	 * Filter Yoast SEO og:image:height on singular post/CPT pages.
	 *
	 * @param mixed $height Default height set by Yoast.
	 *
	 * @return mixed
	 */
	public function filter_yoast_og_image_height( $height ) {
		if ( ! is_singular() ) {
			return $height;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $height;
		}

		$dims = self::get_social_image_dimensions( (int) $post_id );
		return null !== $dims ? $dims['height'] : $height;
	}

	/**
	 * Filter Yoast SEO og:image:type on singular post/CPT pages.
	 *
	 * @param mixed $type Default MIME type set by Yoast.
	 *
	 * @return mixed
	 */
	public function filter_yoast_og_image_type( $type ) {
		if ( ! is_singular() ) {
			return $type;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $type;
		}

		$data = self::get_social_image_data( (int) $post_id );
		return is_array( $data ) && ! empty( $data['type'] ) ? $data['type'] : $type;
	}
}
