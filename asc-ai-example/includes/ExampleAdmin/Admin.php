<?php
/**
 * Admin Class
 *
 * Core admin class that maintains constants, initializes admin components, and
 * provides markup for featured / tag toggles on edit screens.
 * Meta keys and values are defined in {@see \ASC\AI_BOILER\ExampleCore\PostMeta}.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleAdmin;

use ASC\AI_BOILER\Core\RegisterPartials;
use ASC\AI_BOILER\ExampleCore\RegisterProjects;
use ASC\AI_BOILER\ExampleCore\RegisterServices;

/**
 * Admin Class
 */
class Admin {

	/**
	 * Initialize the Admin class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize admin components.
	 *
	 * @return void
	 */
	private function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		new ProjectsAdmin();
		new ServicesAdmin();
		new BlogAdmin();
		new SettingsPage();
	}

	/**
	 * Enqueue admin CSS/JS on the plugin settings page and on CPT/post edit screens (includes toggle UI).
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$screen = get_current_screen();
		if ( $screen === null ) {
			return;
		}

		$on_example_settings_hub = ( $screen->id === 'toplevel_page_' . SettingsPage::PAGE_SLUG );

		$edit_post_types = array(
			RegisterProjects::POST_TYPE,
			RegisterServices::POST_TYPE,
			RegisterPartials::POST_TYPE,
			'post',
		);

		$on_edit_screen = in_array( $screen->post_type, $edit_post_types, true )
			&& in_array( $screen->base, array( 'post', 'edit' ), true );

		if ( ! $on_example_settings_hub && ! $on_edit_screen ) {
			return;
		}

		$plugin_url = plugin_dir_url( \ASC_AI_EXAMPLE_PLUGIN_FILE );
		$plugin_path = plugin_dir_path( \ASC_AI_EXAMPLE_PLUGIN_FILE );
		$css_file = 'assets/example-admin/admin.css';
		$js_file = 'assets/example-admin/admin.js';

		wp_enqueue_style(
			'example_site_admin',
			$plugin_url . $css_file,
			array(),
			filemtime( $plugin_path . $css_file )
		);

		wp_enqueue_script(
			'example_site_admin',
			$plugin_url . $js_file,
			array( 'jquery' ),
			filemtime( $plugin_path . $js_file ),
			true
		);

		$needs_media_library = $on_example_settings_hub;
		if ( $on_edit_screen
			&& RegisterProjects::POST_TYPE === $screen->post_type
			&& 'post' === $screen->base
		) {
			$needs_media_library = true;
		}

		if ( $needs_media_library ) {
			wp_enqueue_media();
		}

		$localized = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( 'asc-ai-boiler-admin-ajax-nonce' ),
		);

		wp_localize_script(
			'example_site_admin',
			'example_site_admin',
			$localized
		);
	}

	/**
	 * Render featured toggle HTML.
	 *
	 * @param bool $checked Whether featured toggle is enabled.
	 *
	 * @return string
	 */
	public static function get_featured_toggle_html( bool $checked ): string {
		$checked_attr = '';
		if ( $checked ) {
			$checked_attr = ' checked="checked"';
		}

		return '<label class="example-featured-toggle" for="example_site_featured">'
			. '<input type="checkbox" id="example_site_featured" name="example_site_featured" value="1"' . $checked_attr . ' />'
			. '<span class="example-featured-toggle-slider" aria-hidden="true"></span>'
			. '<span class="example-featured-toggle-label">' . esc_html__( 'Featured', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</span>'
			. '</label>';
	}

	/**
	 * Render new toggle HTML.
	 *
	 * @param bool $checked Whether new toggle is enabled.
	 *
	 * @return string
	 */
	public static function get_new_toggle_html( bool $checked ): string {
		$checked_attr = '';
		if ( $checked ) {
			$checked_attr = ' checked="checked"';
		}

		return '<label class="example-new-toggle" for="example_site_new">'
			. '<input type="checkbox" id="example_site_new" name="example_site_new" value="1"' . $checked_attr . ' />'
			. '<span class="example-featured-toggle-slider" aria-hidden="true"></span>'
			. '<span class="example-featured-toggle-label">' . esc_html__( 'New', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</span>'
			. '</label>';
	}

	/**
	 * Render a mutually-exclusive fixed-tag toggle group.
	 *
	 * @param string $group_name Unique group name.
	 * @param string $selected_tag_slug Selected tag slug.
	 * @param array<string> $tag_slugs Allowed tag slugs.
	 *
	 * @return string
	 */
	public static function get_tag_toggle_group_html( string $group_name, string $selected_tag_slug, array $tag_slugs ): string {
		$markup = '<div class="example-tag-toggle-group" data-example-tag-group="' . esc_attr( $group_name ) . '">';
		$markup .= '<p><strong>' . esc_html__( 'Tag', \ASC_AI_BOILER_TEXT_DOMAIN ) . '</strong></p>';

		foreach ( $tag_slugs as $tag_slug ) {
			$checked_attr = '';
			if ( $selected_tag_slug === $tag_slug ) {
				$checked_attr = ' checked="checked"';
			}

			$input_id = 'example_site_tag_' . sanitize_html_class( $group_name ) . '_' . sanitize_html_class( $tag_slug );
			$input_name = 'example_site_tag_' . sanitize_key( $tag_slug );
			$label_text = ucwords( str_replace( '-', ' ', $tag_slug ) );

			$markup .= '<label class="example-tag-toggle" for="' . esc_attr( $input_id ) . '">';
			$markup .= '<input type="checkbox" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="1" data-example-tag-choice="' . esc_attr( $tag_slug ) . '"' . $checked_attr . ' />';
			$markup .= '<span class="example-featured-toggle-slider" aria-hidden="true"></span>';
			$markup .= '<span class="example-featured-toggle-label">' . esc_html( $label_text ) . '</span>';
			$markup .= '</label>';
		}

		$markup .= '</div>';

		return $markup;
	}
}
