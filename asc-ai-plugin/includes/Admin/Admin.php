<?php
/**
 * Boiler admin only: Import / Export screen, sync AJAX wiring, and `assets/admin/` assets.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

use ASC\AI_BOILER\Core\Core;

/**
 * Boiler Admin (Import / Export). No front-end responsibilities.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		new SettingsPage();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 5 );
	}

	/**
	 * Enqueue CSS/JS for the Import / Export screen only.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$sync_hook_suffix = SettingsPage::admin_hook_suffix();
		if ( '' !== $sync_hook_suffix && $hook_suffix === $sync_hook_suffix ) {
			$this->enqueue_import_export_assets();
			return;
		}

		$screen = get_current_screen();
		if ( $screen === null ) {
			return;
		}

		if ( $screen->id === SettingsPage::screen_id() ) {
			$this->enqueue_import_export_assets();
		}
	}

	/**
	 * Enqueue Import / Export screen assets.
	 *
	 * @return void
	 */
	private function enqueue_import_export_assets(): void {
		$core = Core::get_instance();
		$plugin_url = $core->get_plugin_url();
		$plugin_path = $core->get_plugin_path();
		$css_rel = 'assets/admin/admin.css';
		$js_rel = 'assets/admin/admin.js';

		wp_enqueue_style(
			'asc_ai_boiler_admin',
			$plugin_url . $css_rel,
			array(),
			filemtime( $plugin_path . $css_rel )
		);

		wp_enqueue_script(
			'asc_ai_boiler_admin',
			$plugin_url . $js_rel,
			array( 'jquery' ),
			filemtime( $plugin_path . $js_rel ),
			true
		);

		$localized = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'sync' => array(
				'nonce' => wp_create_nonce( ContentSync::nonce_action() ),
				'import_action' => ContentSync::AJAX_ACTION_IMPORT_BATCH,
				'export_action' => ContentSync::AJAX_ACTION_EXPORT_BATCH,
				'detect_action' => ContentSync::AJAX_ACTION_DETECT_DIFFERENCES,
				'batch_size' => SyncConfig::CONTENT_SYNC_BATCH_SIZE,
				'import_auto_confirm' => SyncConfig::is_development_mode(),
				'strings' => array(
					'import_starting' => __( 'Import starting…', \ASC_AI_PLUGIN_DOMAIN ),
					'import_progress' => __( 'Importing: processed %1$s of %2$s plugin files…', \ASC_AI_PLUGIN_DOMAIN ),
					'import_complete' => __( 'Import finished. %1$s posts/files updated out of %2$s scanned.', \ASC_AI_PLUGIN_DOMAIN ),
					'export_starting' => __( 'Export starting…', \ASC_AI_PLUGIN_DOMAIN ),
					'export_progress' => __( 'Export: +%1$s file(s), +%2$s manifest metadata refresh(es). Totals: %3$s files, %4$s metadata.', \ASC_AI_PLUGIN_DOMAIN ),
					'export_complete' => __( 'Export finished. Wrote %s HTML file(s). Manifest and orphan cleanup completed.', \ASC_AI_PLUGIN_DOMAIN ),
					'export_complete_with_meta' => __( 'Export finished. Wrote %1$s HTML file(s); manifest metadata refreshed for %2$s post(s) whose plugin files already matched WordPress. Manifest and orphan cleanup completed.', \ASC_AI_PLUGIN_DOMAIN ),
					'failure' => __( 'Export/import failed: %s', \ASC_AI_PLUGIN_DOMAIN ),
					'confirm_required' => __( 'Confirm the import checkbox before running an import.', \ASC_AI_PLUGIN_DOMAIN ),
					'detect_working' => __( 'Comparing plugin files and WordPress content…', \ASC_AI_PLUGIN_DOMAIN ),
					'detect_fail' => __( 'Could not compare content: %s', \ASC_AI_PLUGIN_DOMAIN ),
					'detect_none' => __( 'No differences found. Published WordPress content matches plugin export files (HTML content, titles, slugs, categories, tags, excerpts, meta descriptions, publication date, and media library files).', \ASC_AI_PLUGIN_DOMAIN ),
					'detect_heading' => __( 'Differences', \ASC_AI_PLUGIN_DOMAIN ),
				),
			),
		);

		wp_localize_script(
			'asc_ai_boiler_admin',
			'asc_ai_boiler_admin',
			$localized
		);
	}
}
