<?php
/**
 * Decomposed class.
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WP_Post;
use WP_Query;
use ASC\AI_BOILER\Core\Core;

final class SyncAjaxHandler {

	public const AJAX_ACTION_IMPORT_BATCH = 'asc_ai_boiler_import_batch';
	public const AJAX_ACTION_EXPORT_BATCH = 'asc_ai_boiler_export_batch';
	public const AJAX_ACTION_DETECT_DIFFERENCES = 'asc_ai_boiler_detect_differences';
	public const NONCE_ACTION = 'asc_ai_boiler_sync';

	/**
	 * Nonce action for static content sync AJAX.
	 *
	 * @return string
	 */
	public static function nonce_action(): string {
		return self::NONCE_ACTION;
	}

	/**
	 * AJAX: compare plugin files to WordPress published content (no writes).
	 *
	 * @return void
	 */
	public static function handle_ajax_detect_differences(): void {
		check_ajax_referer( self::nonce_action() );

		if ( ! SyncConfig::is_sync_page_enabled() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Import/Export is disabled in settings.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to detect differences.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				403
			);
		}

		$result = ContentSync::run_detect_content_differences();
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: run one import batch (see {@see ContentImporter::run_import_batch()}).
	 *
	 * @return void
	 */
	public static function handle_ajax_import_batch(): void {
		check_ajax_referer( self::nonce_action() );

		if ( ! SyncConfig::is_sync_page_enabled() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Import/Export is disabled in settings.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to run import.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				403
			);
		}

		$offset = 0;
		if ( isset( $_POST['offset'] ) ) {
			$offset = absint( (string) wp_unslash( $_POST['offset'] ) );
		}
		$confirmed = SyncConfig::is_development_mode();
		if ( ! $confirmed && isset( $_POST['confirmed'] ) && '1' === (string) wp_unslash( $_POST['confirmed'] ) ) {
			$confirmed = true;
		}

		$result = ContentImporter::run_import_batch( $offset, $confirmed );
		if ( ! $result['ok'] ) {
			$fallback = __( 'Import failed.', \ASC_AI_PLUGIN_DOMAIN );
			$msg = $result['messages'][0] ?? $fallback;
			wp_send_json_error( array( 'message' => $msg ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: run one export batch (see {@see ContentExporter::run_export_batch()}).
	 *
	 * @return void
	 */
	public static function handle_ajax_export_batch(): void {
		check_ajax_referer( self::nonce_action() );

		if ( ! SyncConfig::is_sync_page_enabled() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Import/Export is disabled in settings.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to run export.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				403
			);
		}

		$type_index = 0;
		if ( isset( $_POST['type_index'] ) ) {
			$type_index = absint( (string) wp_unslash( $_POST['type_index'] ) );
		}
		$post_offset = 0;
		if ( isset( $_POST['post_offset'] ) ) {
			$post_offset = absint( (string) wp_unslash( $_POST['post_offset'] ) );
		}

		$result = ContentExporter::run_export_batch( $type_index, $post_offset );
		if ( ! $result['ok'] ) {
			$batch_fail = __( 'Export batch failed.', \ASC_AI_PLUGIN_DOMAIN );
			wp_send_json_error( array( 'message' => $batch_fail ) );
		}

		wp_send_json_success( $result );
	}

}
