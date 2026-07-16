<?php
/**
 * Boiler Settings and Import / Export admin screens.
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
 * Admin interface pages for aS.c Boiler Settings and static sync operations.
 */
class SettingsPage {

	/**
	 * Main Settings page slug.
	 *
	 * @var string
	 */
	public const SETTINGS_PAGE_SLUG = 'asc-ai-boiler-settings';

	/**
	 * Import / Export page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'asc-ai-boiler-import-export';

	/**
	 * Admin hook suffix returned by {@see add_menu_page()} for Settings.
	 *
	 * @var string
	 */
	private static string $settings_hook_suffix = '';

	/**
	 * Admin hook suffix returned by {@see add_submenu_page()} for Import / Export.
	 *
	 * @var string
	 */
	private static string $admin_hook_suffix = '';

	/**
	 * Admin-post action for saving settings.
	 *
	 * @var string
	 */
	public const SAVE_FORM_ACTION = 'asc_ai_boiler_save_settings';

	/**
	 * Nonce action for saving settings.
	 *
	 * @var string
	 */
	private const SAVE_SETTINGS_NONCE = 'asc_ai_boiler_save_settings_nonce';

	// POST checkbox fields
	private const POST_SYNC_PAGES = 'asc_ai_boiler_sync_pages';
	private const POST_SYNC_PARTIALS = 'asc_ai_boiler_sync_partials';
	private const POST_SYNC_POSTS = 'asc_ai_boiler_sync_posts';
	private const POST_SYNC_CUSTOM_POST_TYPES = 'asc_ai_boiler_sync_custom_post_types';
	private const POST_SYNC_MEDIA = 'asc_ai_boiler_sync_media';
	private const POST_ENABLE_SYNC_PAGE = 'asc_ai_boiler_enable_sync_page';
	private const POST_EXPORT_CLEANUP = 'asc_ai_boiler_export_cleanup';
	private const POST_IMPORT_CLEANUP = 'asc_ai_boiler_import_cleanup';
	private const POST_DEVELOPMENT_MODE = 'asc_ai_boiler_development_mode';
	private const POST_YOAST_SYNC = 'asc_ai_boiler_yoast_sync';
	private const POST_COMPANION_SLUG = 'asc_ai_boiler_companion_slug';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( RegisterPartials::FILTER_ADMIN_MENU_PARENT, static fn(): string => self::SETTINGS_PAGE_SLUG );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_post_' . self::SAVE_FORM_ACTION, array( $this, 'handle_save_sync_settings' ) );

		if ( SyncConfig::is_sync_page_enabled() ) {
			add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_IMPORT_BATCH, array( ContentSync::class, 'handle_ajax_import_batch' ) );
			add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_EXPORT_BATCH, array( ContentSync::class, 'handle_ajax_export_batch' ) );
			add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_DETECT_DIFFERENCES, array( ContentSync::class, 'handle_ajax_detect_differences' ) );
		}
	}

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$parent_slug = self::SETTINGS_PAGE_SLUG;

		$settings_hook = add_menu_page(
			__( 'aS.c Boiler', \ASC_AI_PLUGIN_DOMAIN ),
			__( 'aS.c Boiler', \ASC_AI_PLUGIN_DOMAIN ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_settings_page' ),
			'dashicons-admin-generic',
			57
		);

		if ( is_string( $settings_hook ) ) {
			self::$settings_hook_suffix = $settings_hook;
		}

		add_submenu_page(
			$parent_slug,
			__( 'Settings', \ASC_AI_PLUGIN_DOMAIN ),
			__( 'Settings', \ASC_AI_PLUGIN_DOMAIN ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_settings_page' )
		);

		if ( SyncConfig::is_sync_page_enabled() ) {
			$sync_hook = add_submenu_page(
				$parent_slug,
				__( 'Import / Export', \ASC_AI_PLUGIN_DOMAIN ),
				__( 'Import / Export', \ASC_AI_PLUGIN_DOMAIN ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_import_export_page' )
			);

			if ( is_string( $sync_hook ) ) {
				self::$admin_hook_suffix = $sync_hook;
			}
		}
	}

	/**
	 * Hook suffix for Settings page.
	 *
	 * @return string
	 */
	public static function settings_hook_suffix(): string {
		return self::$settings_hook_suffix;
	}

	/**
	 * Hook suffix for Import/Export page.
	 *
	 * @return string
	 */
	public static function admin_hook_suffix(): string {
		return self::$admin_hook_suffix;
	}

	/**
	 * Screen ID for Settings page.
	 *
	 * @return string
	 */
	public static function settings_screen_id(): string {
		return self::SETTINGS_PAGE_SLUG;
	}

	/**
	 * Screen ID for Import/Export page.
	 *
	 * @return string
	 */
	public static function screen_id(): string {
		return self::PAGE_SLUG;
	}

	/**
	 * Persist all options.
	 *
	 * @return void
	 */
	public function handle_save_sync_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update settings.', \ASC_AI_PLUGIN_DOMAIN ) );
		}

		check_admin_referer( self::SAVE_SETTINGS_NONCE );

		SyncConfig::set_pages_sync_enabled( isset( $_POST[ self::POST_SYNC_PAGES ] ) );
		SyncConfig::set_partials_sync_enabled( isset( $_POST[ self::POST_SYNC_PARTIALS ] ) );
		SyncConfig::set_posts_sync_enabled( isset( $_POST[ self::POST_SYNC_POSTS ] ) );
		SyncConfig::set_custom_post_types_sync_enabled( isset( $_POST[ self::POST_SYNC_CUSTOM_POST_TYPES ] ) );
		SyncConfig::set_media_sync_enabled( isset( $_POST[ self::POST_SYNC_MEDIA ] ) );
		SyncConfig::set_sync_page_enabled( isset( $_POST[ self::POST_ENABLE_SYNC_PAGE ] ) );

		SyncConfig::set_export_cleanup( isset( $_POST[ self::POST_EXPORT_CLEANUP ] ) );
		SyncConfig::set_import_cleanup( isset( $_POST[ self::POST_IMPORT_CLEANUP ] ) );
		SyncConfig::set_development_mode( isset( $_POST[ self::POST_DEVELOPMENT_MODE ] ) );
		SyncConfig::set_yoast_sync( isset( $_POST[ self::POST_YOAST_SYNC ] ) );
		SyncConfig::set_companion_slug( isset( $_POST[ self::POST_COMPANION_SLUG ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::POST_COMPANION_SLUG ] ) ) : '' );

		$redirect_url = add_query_arg(
			array(
				'page' => self::SETTINGS_PAGE_SLUG,
				'settings-updated' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sync_pages             = SyncConfig::is_pages_sync_enabled();
		$sync_partials          = SyncConfig::is_partials_sync_enabled();
		$sync_posts             = SyncConfig::is_posts_sync_enabled();
		$sync_custom_post_types = SyncConfig::is_custom_post_types_sync_enabled();
		$sync_media             = SyncConfig::is_media_sync_enabled();
		$enable_sync_page       = SyncConfig::is_sync_page_enabled();

		$export_delete_orphans  = SyncConfig::is_export_cleanup();
		$import_cleanup         = SyncConfig::is_import_cleanup();
		$import_dev_mode        = SyncConfig::is_development_mode();
		$yoast_sync             = SyncConfig::is_yoast_sync();
		$companion_slug         = SyncConfig::get_companion_slug();

		?>
		<div class="wrap asc-ai-boiler-settings-page">
			<div class="asc-ai-boiler-header">
				<h1><?php esc_html_e( 'aS.c Boiler Settings', \ASC_AI_PLUGIN_DOMAIN ); ?></h1>
				<p class="description"><?php esc_html_e( 'Configure synchronization settings.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
			</div>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="asc-ai-boiler-settings-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_FORM_ACTION ); ?>">
				<?php wp_nonce_field( self::SAVE_SETTINGS_NONCE ); ?>

				<div class="asc-ai-boiler-card-grid">
					<!-- Card: General Settings -->
					<div class="asc-ai-boiler-card">
						<h2>
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'General Settings', \ASC_AI_PLUGIN_DOMAIN ); ?>
						</h2>
						<p class="card-intro"><?php esc_html_e( 'Configure the companion plugin integration and sync page access.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>

						<div class="settings-checkbox-list">
							<div class="settings-checkbox-item" style="display: flex; flex-direction: column; gap: 8px;">
								<label for="companion-slug">
									<strong><?php esc_html_e( 'Companion Plugin Slug', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<input type="text" name="<?php echo esc_attr( self::POST_COMPANION_SLUG ); ?>" id="companion-slug" class="regular-text" value="<?php echo esc_attr( $companion_slug ); ?>" placeholder="e.g. asc-ai-example" style="max-width: 100%;">
								<p class="description" style="margin-top: 0;">
									<?php esc_html_e( 'The folder name of the companion plugin in the wp-content/plugins directory.', \ASC_AI_PLUGIN_DOMAIN ); ?>
								</p>
							</div>

							<?php
							$companion_paths = SyncConfig::get_companion_paths();
							if ( $companion_paths ) :
								?>
								<div class="companion-status status-success" style="padding: 10px; background: #e7f4e4; border-left: 4px solid #46b450; border-radius: 2px;">
									<p style="margin: 0 0 5px 0;"><strong><?php esc_html_e( 'Status: Connected', \ASC_AI_PLUGIN_DOMAIN ); ?></strong></p>
									<p style="margin: 0; font-family: monospace; font-size: 11px; word-break: break-all;">
										<?php echo esc_html( $companion_paths['content_dir'] ); ?>
									</p>
									<p style="margin: 5px 0 0 0; font-size: 12px;">
										<?php
										if ( $companion_paths['is_active'] ) {
											esc_html_e( 'Companion plugin is currently active.', \ASC_AI_PLUGIN_DOMAIN );
										} else {
											esc_html_e( 'Companion plugin is installed but inactive.', \ASC_AI_PLUGIN_DOMAIN );
										}
										?>
									</p>
								</div>
							<?php elseif ( ! empty( $companion_slug ) ) : ?>
								<div class="companion-status status-error" style="padding: 10px; background: #fbeae5; border-left: 4px solid #dc3232; border-radius: 2px;">
									<p style="margin: 0 0 5px 0; color: #d01111;"><strong><?php esc_html_e( 'Status: Directory Not Found', \ASC_AI_PLUGIN_DOMAIN ); ?></strong></p>
									<p style="margin: 0; font-size: 12px;">
										<?php
										/* translators: %s: slug */
										echo sprintf( esc_html__( 'Could not locate plugin directory for slug: %s', \ASC_AI_PLUGIN_DOMAIN ), esc_html( $companion_slug ) );
										?>
									</p>
								</div>
							<?php endif; ?>

							<div class="settings-checkbox-item">
								<label for="enable-sync-page">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_ENABLE_SYNC_PAGE ); ?>" id="enable-sync-page" value="1" <?php checked( $enable_sync_page ); ?>>
									<strong><?php esc_html_e( 'Enable Import / Export Page', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Turn this off when not actively using sync for security and performance.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>
						</div>
					</div>

					<!-- Card 2: Sync Behavior -->
					<div class="asc-ai-boiler-card">
						<h2>
							<span class="dashicons dashicons-admin-settings"></span>
							<?php esc_html_e( 'Sync Behavior', \ASC_AI_PLUGIN_DOMAIN ); ?>
						</h2>
						<p class="card-intro"><?php esc_html_e( 'Adjust the backup, restore, and metadata actions.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>

						<div class="settings-checkbox-list">
							<div class="settings-checkbox-item">
								<label for="import-cleanup">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_IMPORT_CLEANUP ); ?>" id="import-cleanup" value="1" <?php checked( $import_cleanup ); ?>>
									<strong><?php esc_html_e( 'Import Cleanup', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Delete published WordPress content if its plugin HTML file is missing on import.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="export-cleanup">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_EXPORT_CLEANUP ); ?>" id="export-cleanup" value="1" <?php checked( $export_delete_orphans ); ?>>
									<strong><?php esc_html_e( 'Export Cleanup', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Delete local plugin content files that have no matching WordPress content on export.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="development-mode">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_DEVELOPMENT_MODE ); ?>" id="development-mode" value="1" <?php checked( $import_dev_mode ); ?>>
									<strong><?php esc_html_e( 'Developer Mode', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Pre-check the confirmation boxes on the sync screens for faster click cycles.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>
						</div>
					</div>

					<!-- Card 3: Content to Sync -->
					<div class="asc-ai-boiler-card">
						<h2>
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'Content Types to Sync', \ASC_AI_PLUGIN_DOMAIN ); ?>
						</h2>
						<p class="card-intro"><?php esc_html_e( 'Choose what types of content to include in sync runs.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>

						<div class="settings-checkbox-list">
							<div class="settings-checkbox-item">
								<label for="sync-pages">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_SYNC_PAGES ); ?>" id="sync-pages" value="1" <?php checked( $sync_pages ); ?>>
									<strong><?php esc_html_e( 'Pages', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Sync standard pages under content/pages.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="sync-partials">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_SYNC_PARTIALS ); ?>" id="sync-partials" value="1" <?php checked( $sync_partials ); ?>>
									<strong><?php esc_html_e( 'Partials', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Sync reusable partial layout files under content/partials.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="sync-posts">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_SYNC_POSTS ); ?>" id="sync-posts" value="1" <?php checked( $sync_posts ); ?>>
									<strong><?php esc_html_e( 'Posts', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Sync standard posts under content/posts.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="sync-cpts">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_SYNC_CUSTOM_POST_TYPES ); ?>" id="sync-cpts" value="1" <?php checked( $sync_custom_post_types ); ?>>
									<strong><?php esc_html_e( 'Custom Post Types', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Sync custom post types defined under content.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="sync-media">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_SYNC_MEDIA ); ?>" id="sync-media" value="1" <?php checked( $sync_media ); ?>>
									<strong><?php esc_html_e( 'Media Library', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Sync media files under content/media.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>

							<div class="settings-checkbox-item">
								<label for="yoast-sync">
									<input type="checkbox" name="<?php echo esc_attr( self::POST_YOAST_SYNC ); ?>" id="yoast-sync" value="1" <?php checked( $yoast_sync ); ?>>
									<strong><?php esc_html_e( 'Yoast SEO Sync', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Sync Yoast SEO data under content/meta-descriptions.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<div class="asc-ai-boiler-submit-wrap">
					<?php submit_button( __( 'Save Settings', \ASC_AI_PLUGIN_DOMAIN ), 'primary large' ); ?>
				</div>
			</form>
		</div>
		<?php
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

		if ( ! SyncConfig::is_sync_page_enabled() ) {
			?>
			<div class="wrap asc-ai-boiler-settings-page">
				<h1><?php esc_html_e( 'Import / Export', \ASC_AI_PLUGIN_DOMAIN ); ?></h1>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'Access Denied: The Import / Export page is currently disabled in the settings.', \ASC_AI_PLUGIN_DOMAIN ); ?></strong></p>
					<p><?php echo sprintf(
						/* translators: %s: URL to the settings page */
						__( 'You can enable it by visiting the <a href="%s">aS.c Boiler Settings</a> page.', \ASC_AI_PLUGIN_DOMAIN ),
						esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG ) )
					); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$import_cleanup = SyncConfig::is_import_cleanup();
		$import_dev_mode = SyncConfig::is_development_mode();
		$auto_confirm_attr = $import_dev_mode ? '1' : '0';

		?>
		<div class="wrap asc-ai-boiler-settings-page">
			<div class="asc-ai-boiler-header">
				<h1><?php esc_html_e( 'aS.c Boiler Import / Export', \ASC_AI_PLUGIN_DOMAIN ); ?></h1>
				<p class="description"><?php esc_html_e( 'Synchronize WordPress database content with plugin workspace files.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
			</div>

			<div class="asc-ai-boiler-sync-grid">
				<div class="asc-ai-boiler-card asc-ai-boiler-sync-card">
					<h2>
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Differences and Status', \ASC_AI_PLUGIN_DOMAIN ); ?>
					</h2>
					<p class="card-intro"><?php esc_html_e( 'Compare files on disk with published content to find conflicts before syncing.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>

					<div class="asc-ai-boiler-diff-highlight" id="asc-ai-boiler-diff-highlight" aria-live="polite"></div>

					<p class="asc-ai-boiler-settings-page__sync-detect-wrap">
						<button type="button" class="button button-primary button-large" id="asc-ai-boiler-detect-difference"><?php esc_html_e( 'Detect Differences', \ASC_AI_PLUGIN_DOMAIN ); ?></button>
					</p>
				</div>

				<div class="asc-ai-boiler-card asc-ai-boiler-sync-card" id="asc-ai-boiler-sync-block" data-asc-ai-boiler-import-auto-confirm="<?php echo esc_attr( $auto_confirm_attr ); ?>">
					<h2>
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Database Import', \ASC_AI_PLUGIN_DOMAIN ); ?>
					</h2>
					<p class="card-intro"><?php esc_html_e( 'Read static files on disk and overwrite WordPress database entries.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>

					<div class="asc-ai-boiler-sync-actions asc-ai-boiler-settings-page__sync-form">
						<?php if ( $import_cleanup ) : ?>
							<div class="notice notice-warning inline">
								<p><?php esc_html_e( 'Import cleanup is enabled: finishing import will move WordPress posts to the trash when their plugin HTML file is missing.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>
						<?php endif; ?>

						<div class="settings-checkbox-item">
							<label for="asc-ai-boiler-import-confirm">
								<input type="checkbox" id="asc-ai-boiler-import-confirm" value="1"<?php checked( $import_dev_mode ); ?>>
								<strong><?php esc_html_e( 'Confirm Import', \ASC_AI_PLUGIN_DOMAIN ); ?></strong>
							</label>
							<p class="description"><?php esc_html_e( 'I understand that import will overwrite database post content with markup from plugin files.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
						</div>

						<p class="description">
							<?php esc_html_e( 'Import publishes content to the WordPress database from the plugin content files. The existing content-manifest.json is regenerated.', \ASC_AI_PLUGIN_DOMAIN ); ?>
						</p>

						<p class="asc-ai-boiler-sync-actions__import">
							<button type="button" class="button button-secondary button-large" id="asc-ai-boiler-import-submit"><?php esc_html_e( 'Import from plugin files', \ASC_AI_PLUGIN_DOMAIN ); ?></button>
						</p>
					</div>

					<div class="asc-ai-boiler-sync-status" id="asc-ai-boiler-import-status">
						<p class="description" id="asc-ai-boiler-import-progress" aria-live="polite"></p>
						<div id="asc-ai-boiler-import-messages" class="asc-ai-boiler-settings-page__sync-ajax-messages"></div>
					</div>
				</div>

				<div class="asc-ai-boiler-card asc-ai-boiler-sync-card">
					<h2>
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Database Export', \ASC_AI_PLUGIN_DOMAIN ); ?>
					</h2>
					<p class="card-intro"><?php esc_html_e( 'Dump WordPress database content out to static files.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>

					<div class="asc-ai-boiler-sync-form">
						<?php if ( SyncConfig::is_export_cleanup() ) : ?>
							<div class="notice notice-warning inline">
								<p><?php esc_html_e( 'Export cleanup is enabled: finishing export will delete plugin content files that have no matching published WordPress content.', \ASC_AI_PLUGIN_DOMAIN ); ?></p>
							</div>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Export writes published content to plugin content files. The existing content-manifest.json is regenerated.', \ASC_AI_PLUGIN_DOMAIN ); ?>
						</p>
						<p class="asc-ai-boiler-sync-actions__export">
							<button type="button" class="button button-primary button-large" id="asc-ai-boiler-export-submit"><?php esc_html_e( 'Export to plugin files', \ASC_AI_PLUGIN_DOMAIN ); ?></button>
						</p>
					</div>

					<div class="asc-ai-boiler-sync-status" id="asc-ai-boiler-export-status">
						<p class="description" id="asc-ai-boiler-export-progress" aria-live="polite"></p>
						<div id="asc-ai-boiler-export-messages" class="asc-ai-boiler-settings-page__sync-ajax-messages"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

}
