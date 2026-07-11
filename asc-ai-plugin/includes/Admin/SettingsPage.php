<?php
/**
 * Boiler Import / Export admin screen: top-level menu, form post handler, and AJAX hooks for static sync.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Core\RegisterPartials;

/**
 * Top-level admin page for Import / Export and parent menu for Partials.
 */
class SettingsPage {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'asc-ai-boiler-import-export';

	/**
	 * Admin hook suffix returned by {@see add_menu_page()} for this screen.
	 *
	 * @var string
	 */
	private static string $admin_hook_suffix = '';

	/**
	 * Admin-post action for saving Import / Export checkboxes.
	 *
	 * @var string
	 */
	public const SAVE_FORM_ACTION = 'asc_ai_boiler_save_settings';

	/**
	 * Nonce action for saving Import / Export settings.
	 *
	 * @var string
	 */
	private const SAVE_SETTINGS_NONCE = 'asc_ai_boiler_save_settings_nonce';

	/**
	 * POST field: export deletes orphan plugin files.
	 *
	 * @var string
	 */
	private const POST_EXPORT_CLEANUP = 'asc_ai_boiler_export_cleanup';

	/**
	 * POST field: import deletes orphan published content.
	 *
	 * @var string
	 */
	private const POST_IMPORT_CLEANUP = 'asc_ai_boiler_import_cleanup';

	/**
	 * POST field: development mode (pre-check import confirmation).
	 *
	 * @var string
	 */
	private const POST_DEVELOPMENT_MODE = 'asc_ai_boiler_development_mode';

	/**
	 * POST field: Yoast SEO integration sync.
	 *
	 * @var string
	 */
	private const POST_YOAST_SYNC = 'asc_ai_boiler_yoast_sync';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( RegisterPartials::FILTER_ADMIN_MENU_PARENT, static fn(): string => self::PAGE_SLUG );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_post_' . self::SAVE_FORM_ACTION, array( $this, 'handle_save_sync_settings' ) );
		add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_IMPORT_BATCH, array( ContentSync::class, 'handle_ajax_import_batch' ) );
		add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_EXPORT_BATCH, array( ContentSync::class, 'handle_ajax_export_batch' ) );
		add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_DETECT_DIFFERENCES, array( ContentSync::class, 'handle_ajax_detect_differences' ) );
	}

	/**
	 * Register top-level menu and self-submenu (removes duplicate top-level label).
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook_suffix = add_menu_page(
			__( 'AI Boiler', \ASC_AI_PLUGIN_DOMAIN ),
			__( 'AI Boiler', \ASC_AI_PLUGIN_DOMAIN ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_import_export_page' ),
			'dashicons-admin-generic',
			57
		);

		if ( is_string( $hook_suffix ) ) {
			self::$admin_hook_suffix = $hook_suffix;
		}

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Import / Export', \ASC_AI_PLUGIN_DOMAIN ),
			__( 'Import / Export', \ASC_AI_PLUGIN_DOMAIN ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_import_export_page' )
		);
	}

	/**
	 * Hook suffix for this screen (for {@see Admin::enqueue_admin_assets()}).
	 *
	 * @return string
	 */
	public static function admin_hook_suffix(): string {
		return self::$admin_hook_suffix;
	}

	/**
	 * Screen ID for this page.
	 *
	 * @return string
	 */
	public static function screen_id(): string {
		return self::PAGE_SLUG;
	}

	/**
	 * Persist sync settings checkboxes.
	 *
	 * @return void
	 */
	public function handle_save_sync_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update settings.', \ASC_AI_PLUGIN_DOMAIN ) );
		}

		check_admin_referer( self::SAVE_SETTINGS_NONCE );

		SyncConfig::set_export_cleanup( isset( $_POST[ self::POST_EXPORT_CLEANUP ] ) );
		SyncConfig::set_import_cleanup( isset( $_POST[ self::POST_IMPORT_CLEANUP ] ) );
		SyncConfig::set_development_mode( isset( $_POST[ self::POST_DEVELOPMENT_MODE ] ) );
		SyncConfig::set_yoast_sync( isset( $_POST[ self::POST_YOAST_SYNC ] ) );

		$redirect_url = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'settings-updated' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render static sync page.
	 *
	 * @return void
	 */
	public function render_import_export_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$export_delete_orphans = SyncConfig::is_export_cleanup();
		$import_cleanup = SyncConfig::is_import_cleanup();
		$import_dev_mode = SyncConfig::is_development_mode();
		$yoast_sync = SyncConfig::is_yoast_sync();
		$auto_confirm_attr = $import_dev_mode ? '1' : '0';

		?>
		<div class="wrap asc-ai-boiler-settings-page">
			<h1><?php esc_html_e( 'Import / Export', \ASC_AI_PLUGIN_DOMAIN ); ?></h1>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Import / Export settings', \ASC_AI_PLUGIN_DOMAIN ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="asc-ai-boiler-settings-page__default-form asc-ai-boiler-settings-page__sync-settings-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_FORM_ACTION ); ?>">
				<?php wp_nonce_field( self::SAVE_SETTINGS_NONCE ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Export cleanup', \ASC_AI_PLUGIN_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-export-cleanup">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_EXPORT_CLEANUP ); ?>"
										id="asc-ai-boiler-export-cleanup"
										value="1"
										<?php checked( $export_delete_orphans ); ?>
									>
									<?php esc_html_e( 'After export, delete plugin content files that have no matching published WordPress content.', \ASC_AI_PLUGIN_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Use when WordPress content was removed on purpose.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Import cleanup', \ASC_AI_PLUGIN_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-import-cleanup">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_IMPORT_CLEANUP ); ?>"
										id="asc-ai-boiler-import-cleanup"
										value="1"
										<?php checked( $import_cleanup ); ?>
									>
									<?php esc_html_e( 'After import, delete published WordPress content that has no matching plugin content files.', \ASC_AI_PLUGIN_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Use when plugin export files were removed on purpose.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Developer mode', \ASC_AI_PLUGIN_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-import-development-mode">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_DEVELOPMENT_MODE ); ?>"
										id="asc-ai-boiler-import-development-mode"
										value="1"
										<?php checked( $import_dev_mode ); ?>
									>
									<?php esc_html_e( 'Pre-check the import confirmation below.', \ASC_AI_PLUGIN_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Use for one-click import from new plugin files by having the confirmation checkbox always checked.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Yoast SEO settings', \ASC_AI_PLUGIN_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-yoast-sync">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_YOAST_SYNC ); ?>"
										id="asc-ai-boiler-yoast-sync"
										value="1"
										<?php checked( $yoast_sync ); ?>
									>
									<?php esc_html_e( 'Import/Export Yoast settings on sync.', \ASC_AI_PLUGIN_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'If unchecked, Yoast SEO fields are ignored and not updated or exported.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Import / Export settings', \ASC_AI_PLUGIN_DOMAIN ) ); ?>
			</form>

			<hr>

			<div class="asc-ai-boiler-settings-page__sync" id="asc-ai-boiler-sync-block" data-asc-ai-boiler-import-auto-confirm="<?php echo esc_attr( $auto_confirm_attr ); ?>">
				<p class="description">
					<?php esc_html_e( 'Synchronize WordPress published content with plugin content files under the content directory.', \ASC_AI_PLUGIN_DOMAIN ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Export writes all published pages, posts, partials, and custom post types from the WordPress database to the plugin content files, including publication and modification dates, page/post title, page/post slug, tags, categories, excerpts, meta descriptions, Yoast SEO settings (focus keyphrases, social titles, and social descriptions), and WordPress media library files. Whether orphaned plugin files are removed afterward depends on the export cleanup setting above.', \ASC_AI_PLUGIN_DOMAIN ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Import updates all published pages, posts, partials, and custom post types from the plugin content files to the WordPress database, using the manifest for publication time, page/post title, page/post slug, tags, categories, excerpts, meta descriptions, Yoast SEO settings (focus keyphrases, social titles, and social descriptions), and WordPress media library files when applicable. Last modified time in WordPress is not taken from the manifest. When import finishes, plugin HTML and content-manifest.json on disk are rewritten to canonical export form from WordPress. Whether orphaned published WordPress content is removed from the WordPress database afterward depends on the import cleanup setting above.', \ASC_AI_PLUGIN_DOMAIN ); ?>
				</p>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of items per AJAX batch */
							__( 'Each export or import run processes up to %d published posts or plugin files per request, so large sites stay within PHP time limits.', \ASC_AI_PLUGIN_DOMAIN ),
							SyncConfig::CONTENT_SYNC_BATCH_SIZE
						)
					);
					?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Import brings plugin HTML and content/media/ into WordPress (including the media library, default images, and featured images via manifest bindings), along with Yoast SEO integration fields (when enabled). Other post metadata (for example custom fields from advanced plugins) is not synced. Configure that data separately on each WordPress instance when needed.', \ASC_AI_PLUGIN_DOMAIN ); ?>
				</p>
				<div class="asc-ai-boiler-diff-highlight" id="asc-ai-boiler-diff-highlight" aria-live="polite"></div>
				<p class="asc-ai-boiler-settings-page__sync-detect-wrap">
					<button type="button" class="button" id="asc-ai-boiler-detect-difference"><?php esc_html_e( 'Detect Differences', \ASC_AI_PLUGIN_DOMAIN ); ?></button>
				</p>
				<p class="asc-ai-boiler-sync-actions__export">
					<button type="button" class="button button-primary" id="asc-ai-boiler-export-submit"><?php esc_html_e( 'Export to plugin files', \ASC_AI_PLUGIN_DOMAIN ); ?></button>
				</p>
				<div class="asc-ai-boiler-sync-status" id="asc-ai-boiler-sync-status">
					<p class="description" id="asc-ai-boiler-sync-progress" aria-live="polite"></p>
					<div id="asc-ai-boiler-sync-messages" class="asc-ai-boiler-settings-page__sync-ajax-messages"></div>
				</div>

				<div class="asc-ai-boiler-sync-actions asc-ai-boiler-settings-page__sync-form">
					<?php if ( $import_cleanup ) : ?>
						<p class="description"><?php esc_html_e( 'Import cleanup is enabled: finishing import may move WordPress posts to the trash when their plugin HTML file is missing.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
					<?php endif; ?>
					<p>
						<label class="asc-ai-boiler-settings-page__sync-import-confirm" for="asc-ai-boiler-import-confirm">
							<input type="checkbox" id="asc-ai-boiler-import-confirm" value="1"<?php checked( $import_dev_mode ); ?>>
							<span class="asc-ai-boiler-settings-page__sync-import-confirm-text"><?php esc_html_e( 'I understand that import will overwrite post bodies where plugin file markup differs from WordPress.', \ASC_AI_PLUGIN_DOMAIN ); ?></span>
						</label>
					</p>
					<p class="asc-ai-boiler-sync-actions__import">
						<button type="button" class="button button-secondary" id="asc-ai-boiler-import-submit"><?php esc_html_e( 'Import from plugin files', \ASC_AI_PLUGIN_DOMAIN ); ?></button>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

}
