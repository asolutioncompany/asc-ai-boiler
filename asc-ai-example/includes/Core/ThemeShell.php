<?php
/**
 * Front-end theme shell for asc-ai-example: document rendering and layout filters.
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Front\CallToAction;
use ASC\AI_EXAMPLE\Front\SiteFront;

/**
 * @since 1.0
 * Theme template bypass and document shell for asc-ai-example.
 */
final class ThemeShell {

	public const FILTER_ENABLED = 'asc_ai_boiler_use_theme_shell';
	public const FILTER_INCLUDE_REQUEST = 'asc_ai_boiler_theme_shell_include_request';
	public const FILTER_HEADER = 'asc_ai_boiler_theme_shell_header_markup';
	public const FILTER_FOOTER = 'asc_ai_boiler_theme_shell_footer_markup';
	public const FILTER_BEFORE_MAIN = 'asc_ai_boiler_theme_shell_before_main_markup';
	public const FILTER_AFTER_MAIN = 'asc_ai_boiler_theme_shell_after_main_markup';
	public const FILTER_MAIN = 'asc_ai_boiler_theme_shell_main_markup';
	public const FILTER_MAIN_CLASS = 'asc_ai_boiler_theme_shell_main_class';

	public static function register(): void {
		$enabled = apply_filters( self::FILTER_ENABLED, true );
		if ( ! $enabled ) {
			return;
		}

		add_action( 'after_setup_theme', array( self::class, 'remove_block_theme_templates' ), 100 );
		add_filter( 'template_include', array( self::class, 'filter_template_include' ), 99 );

		add_filter( self::FILTER_HEADER, array( self::class, 'filter_header_markup' ), 10, 1 );
		add_filter( self::FILTER_FOOTER, array( self::class, 'filter_footer_markup' ), 10, 1 );
		add_filter( self::FILTER_AFTER_MAIN, array( self::class, 'filter_after_main_markup' ), 10, 1 );
		add_filter( self::FILTER_MAIN_CLASS, array( self::class, 'filter_main_class' ), 10, 1 );
	}

	public static function remove_block_theme_templates(): void {
		remove_theme_support( 'block-templates' );
	}

	public static function filter_template_include( string $template ): string {
		if ( ! self::should_use_shell_for_request() ) {
			return $template;
		}

		self::render_document();
		exit;
	}

	public static function should_use_shell_for_request(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( is_feed() || is_embed() ) {
			return false;
		}

		if ( is_singular() ) {
			return true;
		}

		if ( is_404() || is_search() || is_archive() || is_home() ) {
			return true;
		}

		$include = apply_filters( self::FILTER_INCLUDE_REQUEST, false );
		return is_bool( $include ) ? $include : false;
	}

	public static function filter_header_markup( string $markup ): string {
		if ( '' !== $markup ) {
			return $markup;
		}

		$site_front = new SiteFront();
		return $site_front->render_header_shortcode();
	}

	public static function filter_footer_markup( string $markup ): string {
		if ( '' !== $markup ) {
			return $markup;
		}

		$site_front = new SiteFront();
		return $site_front->render_footer_shortcode();
	}

	public static function filter_after_main_markup( string $markup ): string {
		$call_to_action = new CallToAction();
		return $markup . $call_to_action->render_cta_shortcode();
	}

	public static function filter_main_class( string $main_class ): string {
		return 'example-main';
	}

	public static function render_document(): void {
		$raw = (string) ( $_COOKIE['asc_cookie'] ?? $_COOKIE['asc-cookie'] ?? '' );
		$color_scheme = 'dark';
		if ( 'asc-light' === $raw ) {
			$color_scheme = 'light';
		}
		?><!DOCTYPE html>
<html <?php language_attributes(); ?> style="color-scheme: <?php echo $color_scheme; ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php self::render_body(); ?>
<?php wp_footer(); ?>
</body>
</html>
		<?php
	}

	private static function render_body(): void {
		$header = apply_filters( self::FILTER_HEADER, '' );
		$before_main = apply_filters( self::FILTER_BEFORE_MAIN, '' );
		$after_main = apply_filters( self::FILTER_AFTER_MAIN, '' );
		$footer = apply_filters( self::FILTER_FOOTER, '' );
		$main_class = apply_filters( self::FILTER_MAIN_CLASS, 'example-main' );

		if ( ! is_string( $header ) ) {
			$header = '';
		}
		if ( ! is_string( $before_main ) ) {
			$before_main = '';
		}
		if ( ! is_string( $after_main ) ) {
			$after_main = '';
		}
		if ( ! is_string( $footer ) ) {
			$footer = '';
		}
		if ( ! is_string( $main_class ) ) {
			$main_class = 'example-main';
		}
		$main_class = trim( $main_class );
		if ( '' === $main_class ) {
			$main_class = 'example-main';
		}

		echo $header;
		echo $before_main;

		echo '<main id="main-content" class="' . esc_attr( $main_class ) . '">';
		self::render_main();
		echo '</main>';

		echo $after_main;
		echo $footer;
	}

	private static function render_main(): void {
		$custom = apply_filters( self::FILTER_MAIN, null );
		if ( is_string( $custom ) ) {
			echo $custom;
			return;
		}

		if ( is_singular() ) {
			self::render_singular_content_only();
			return;
		}

		if ( is_404() ) {
			self::render_default_404();
			return;
		}

		if ( is_search() ) {
			self::render_default_search();
			return;
		}

		self::render_default_archive_loop();
	}

	private static function render_singular_content_only(): void {
		if ( ! have_posts() ) {
			return;
		}

		while ( have_posts() ) {
			the_post();
			$post = get_post();
			$raw = ( $post instanceof \WP_Post ) ? (string) $post->post_content : '';
			echo self::apply_post_content_filters( $raw );
		}
	}

	public static function apply_post_content_filters( string $raw_content ): string {
		$raw_content = str_replace( '[example_home_url]', esc_url( untrailingslashit( home_url() ) ), $raw_content );

		$skip_wpautop = self::markup_is_block_html( $raw_content );
		if ( $skip_wpautop ) {
			remove_filter( 'the_content', 'wpautop' );
			remove_filter( 'the_content', 'shortcode_unautop' );
		}

		$content = apply_filters( 'the_content', $raw_content );
		if ( ! is_string( $content ) ) {
			$content = '';
		}

		if ( $skip_wpautop ) {
			add_filter( 'the_content', 'wpautop' );
			add_filter( 'the_content', 'shortcode_unautop' );
		}

		return str_replace( ']]>', ']]&gt;', $content );
	}

	public static function markup_is_block_html( string $markup ): bool {
		$trimmed = ltrim( str_replace( array( "\r\n", "\r" ), "\n", $markup ) );
		if ( '' === $trimmed ) {
			return false;
		}

		return 1 === preg_match(
			'/^(<(section|div|article|header|footer|nav|main|aside|figure|ul|ol|table|h[1-6])\b|\[)/i',
			$trimmed
		);
	}

	private static function render_default_404(): void {
		echo '<section class="example-full-content">';
		echo '<h1 class="example-page-title">' . esc_html__( 'Page Not Found', 'asc-ai-example' ) . '</h1>';
		echo '<p style="text-align: center;">' . esc_html__( 'The page you requested could not be found.', 'asc-ai-example' ) . '</p>';
		echo '<div class="example-card-section-actions"><a class="example-button-blue" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Return Home →', 'asc-ai-example' ) . '</a></div>';
		echo '</section>';
	}

	private static function render_default_search(): void {
		echo '<section class="example-full-content">';
		echo '<h1 class="example-page-title">';
		printf(
			esc_html__( 'Search results for: %s', 'asc-ai-example' ),
			esc_html( get_search_query() )
		);
		echo '</h1>';

		if ( ! have_posts() ) {
			echo '<p>' . esc_html__( 'No results found.', 'asc-ai-example' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<ul class="example-shell-list">';
		while ( have_posts() ) {
			the_post();
			echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		echo '</ul>';
		echo '</section>';
		wp_reset_postdata();
	}

	private static function render_default_archive_loop(): void {
		echo '<section class="example-full-content">';
		echo '<h1 class="example-page-title">';
		if ( is_home() && ! is_front_page() ) {
			echo esc_html__( 'Blog', 'asc-ai-example' );
		} else {
			echo esc_html( get_the_archive_title() );
		}
		echo '</h1>';

		if ( ! have_posts() ) {
			echo '<p>' . esc_html__( 'No posts found.', 'asc-ai-example' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<ul class="example-shell-list">';
		while ( have_posts() ) {
			the_post();
			echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		echo '</ul>';
		echo '</section>';
		wp_reset_postdata();
	}
}
