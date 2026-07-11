<?php
/**
 * Minimal front-end theme shell: bypass block theme templates and render through plugin layout hooks.
 *
 * Page and post bodies supply main content only; header, footer, and optional bands before the footer
 * are rendered by {@see self::FILTER_HEADER}, {@see self::FILTER_AFTER_MAIN}, and {@see self::FILTER_FOOTER}.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme template bypass and document shell.
 */
final class ThemeShell {

	/**
	 * Filter: return false to leave the active theme templates in control.
	 *
	 * @var string
	 */
	public const FILTER_ENABLED = 'asc_ai_boiler_use_theme_shell';

	/**
	 * Filter: return false to skip the shell on a front-end request.
	 *
	 * @var string
	 */
	public const FILTER_INCLUDE_REQUEST = 'asc_ai_boiler_theme_shell_include_request';

	/**
	 * Filter: markup immediately after {@code <body>} (header partial, skip link, etc.).
	 *
	 * @var string
	 */
	public const FILTER_HEADER = 'asc_ai_boiler_theme_shell_header_markup';

	/**
	 * Filter: markup immediately before {@code </body>} (footer partial).
	 *
	 * @var string
	 */
	public const FILTER_FOOTER = 'asc_ai_boiler_theme_shell_footer_markup';

	/**
	 * Filter: markup after the header and before {@code <main>} opens.
	 *
	 * @var string
	 */
	public const FILTER_BEFORE_MAIN = 'asc_ai_boiler_theme_shell_before_main_markup';

	/**
	 * Filter: markup after {@code </main>} and before the footer (CTA band, etc.).
	 *
	 * @var string
	 */
	public const FILTER_AFTER_MAIN = 'asc_ai_boiler_theme_shell_after_main_markup';

	/**
	 * Filter: replace the entire {@code <main>} body. Return null to use the boiler default.
	 *
	 * @var string
	 */
	public const FILTER_MAIN = 'asc_ai_boiler_theme_shell_main_markup';

	/**
	 * Filter: CSS class list for the {@code <main id="main-content">} element.
	 *
	 * @var string
	 */
	public const FILTER_MAIN_CLASS = 'asc_ai_boiler_theme_shell_main_class';

	/**
	 * @return void
	 */
	public static function register(): void {
		$enabled = apply_filters( self::FILTER_ENABLED, true );
		if ( ! $enabled ) {
			return;
		}

		add_action( 'after_setup_theme', array( self::class, 'remove_block_theme_templates' ), 100 );
		add_filter( 'template_include', array( self::class, 'filter_template_include' ), 99 );
	}

	/**
	 * @return void
	 */
	public static function remove_block_theme_templates(): void {
		remove_theme_support( 'block-templates' );
	}

	/**
	 * @param string $template Path to the theme template WordPress selected.
	 *
	 * @return string
	 */
	public static function filter_template_include( string $template ): string {
		if ( ! self::should_use_shell_for_request() ) {
			return $template;
		}

		$plugin_template = Core::get_instance()->get_plugin_path() . 'templates/boiler-theme-shell.php';
		if ( ! is_readable( $plugin_template ) ) {
			return $template;
		}

		return $plugin_template;
	}

	/**
	 * @return bool
	 */
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
		if ( ! is_bool( $include ) ) {
			$include = false;
		}

		return $include;
	}

	/**
	 * Render the full HTML document (called from {@see templates/boiler-theme-shell.php}).
	 *
	 * @return void
	 */
	public static function render_document(): void {
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
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

	/**
	 * @return void
	 */
	private static function render_body(): void {
		$header = apply_filters( self::FILTER_HEADER, '' );
		$before_main = apply_filters( self::FILTER_BEFORE_MAIN, '' );
		$after_main = apply_filters( self::FILTER_AFTER_MAIN, '' );
		$footer = apply_filters( self::FILTER_FOOTER, '' );
		$main_class = apply_filters( self::FILTER_MAIN_CLASS, 'asc-ai-boiler-main' );

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
			$main_class = 'asc-ai-boiler-main';
		}
		$main_class = trim( $main_class );
		if ( '' === $main_class ) {
			$main_class = 'asc-ai-boiler-main';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filter callbacks return prepared HTML.
		echo $header;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $before_main;

		echo '<main id="main-content" class="' . esc_attr( $main_class ) . '">';
		self::render_main();
		echo '</main>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $after_main;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $footer;
	}

	/**
	 * @return void
	 */
	private static function render_singular_content_only(): void {
		if ( ! have_posts() ) {
			return;
		}

		while ( have_posts() ) {
			the_post();
			$post = get_post();
			$raw = ( $post instanceof \WP_Post ) ? (string) $post->post_content : '';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filter callbacks return prepared HTML.
			echo self::apply_post_content_filters( $raw );
		}
	}

	/**
	 * Run {@see the_content} filters without {@see wpautop()} when markup is block HTML from plugin files.
	 *
	 * WordPress autop runs before shortcodes and inserts {@code <br>} and empty {@code <p>} tags around
	 * {@code <section>}/{@code <div>} layouts and shortcodes, which breaks vertical spacing on synced pages.
	 *
	 * @param string $raw_content Unfiltered post_content.
	 *
	 * @return string
	 */
	public static function apply_post_content_filters( string $raw_content ): string {
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

	/**
	 * Whether stored markup is block HTML (not plain paragraphs) and should skip autop.
	 *
	 * @param string $markup Raw post_content or plugin file body.
	 *
	 * @return bool
	 */
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

	/**
	 * @return void
	 */
	private static function render_main(): void {
		$custom = apply_filters( self::FILTER_MAIN, null );
		if ( is_string( $custom ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

	/**
	 * @return void
	 */
	private static function render_default_404(): void {
		echo '<section class="asc-ai-boiler-shell-content">';
		echo '<h1 class="asc-ai-boiler-shell-title">' . esc_html__( 'Page not found', \ASC_AI_PLUGIN_DOMAIN ) . '</h1>';
		echo '<p>' . esc_html__( 'The page you requested could not be found.', \ASC_AI_PLUGIN_DOMAIN ) . '</p>';
		echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Return home', \ASC_AI_PLUGIN_DOMAIN ) . '</a></p>';
		echo '</section>';
	}

	/**
	 * @return void
	 */
	private static function render_default_search(): void {
		echo '<section class="asc-ai-boiler-shell-content">';
		echo '<h1 class="asc-ai-boiler-shell-title">';
		printf(
			/* translators: %s: search query */
			esc_html__( 'Search results for: %s', \ASC_AI_PLUGIN_DOMAIN ),
			esc_html( get_search_query() )
		);
		echo '</h1>';

		if ( ! have_posts() ) {
			echo '<p>' . esc_html__( 'No results found.', \ASC_AI_PLUGIN_DOMAIN ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<ul class="asc-ai-boiler-shell-list">';
		while ( have_posts() ) {
			the_post();
			echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		echo '</ul>';
		echo '</section>';
		wp_reset_postdata();
	}

	/**
	 * @return void
	 */
	private static function render_default_archive_loop(): void {
		echo '<section class="asc-ai-boiler-shell-content">';
		echo '<h1 class="asc-ai-boiler-shell-title">';
		if ( is_home() && ! is_front_page() ) {
			echo esc_html__( 'Blog', \ASC_AI_PLUGIN_DOMAIN );
		} else {
			echo esc_html( get_the_archive_title() );
		}
		echo '</h1>';

		if ( ! have_posts() ) {
			echo '<p>' . esc_html__( 'No posts found.', \ASC_AI_PLUGIN_DOMAIN ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<ul class="asc-ai-boiler-shell-list">';
		while ( have_posts() ) {
			the_post();
			echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		echo '</ul>';
		echo '</section>';
		wp_reset_postdata();
	}
}
