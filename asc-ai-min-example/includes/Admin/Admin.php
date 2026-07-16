<?php
/**
 * Admin Class
 *
 * Core admin class that maintains constants and initializes admin components.
 *
 * @package asc-ai-min-example
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_MIN_EXAMPLE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Core\RegisterPartials;

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
		new BlogAdmin();
		new SettingsPage();
	}

	/**
	 * Enqueue admin CSS/JS on the plugin settings page and on CPT/post edit screens.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$screen = get_current_screen();
		if ( $screen === null ) {
			return;
		}

		$on_settings_hub = ( $screen->id === 'toplevel_page_' . SettingsPage::PAGE_SLUG );

		$edit_post_types = array(
			RegisterPartials::POST_TYPE,
			'post',
		);

		$on_edit_screen = in_array( $screen->post_type, $edit_post_types, true )
			&& in_array( $screen->base, array( 'post', 'edit' ), true );

		if ( ! $on_settings_hub && ! $on_edit_screen ) {
			return;
		}

		$plugin_url = plugin_dir_url( \ASC_AI_MIN_EXAMPLE_PLUGIN_FILE );
		$plugin_path = plugin_dir_path( \ASC_AI_MIN_EXAMPLE_PLUGIN_FILE );
		$css_file = 'assets/admin/admin.css';
		$js_file = 'assets/admin/admin.js';

		wp_enqueue_style(
			'min_example_site_admin',
			$plugin_url . $css_file,
			array(),
			filemtime( $plugin_path . $css_file )
		);

		wp_enqueue_script(
			'min_example_site_admin',
			$plugin_url . $js_file,
			array( 'jquery' ),
			filemtime( $plugin_path . $js_file ),
			true
		);

		if ( $on_settings_hub ) {
			wp_enqueue_media();
		}

		$localized = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( 'asc-ai-boiler-admin-ajax-nonce' ),
		);

		wp_localize_script(
			'min_example_site_admin',
			'min_example_site_admin',
			$localized
		);
	}
}
