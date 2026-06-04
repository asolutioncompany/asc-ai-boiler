<?php
/**
 * Boiler Backup / Restore admin screen: submenu, form post handler, and AJAX hooks for static sync.
 *
 * @package asc-ai-boiler
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

/**
 * Submenu page for batched backup and restore between disk and WordPress.
 */
class SettingsPage {

	/**
	 * Admin page slug (submenu).
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'asc-ai-boiler-backup-restore';

	/**
	 * Admin hook suffix returned by {@see add_submenu_page()} for this screen.
	 *
	 * @var string
	 */
	private static string $admin_hook_suffix = '';

	/**
	 * Filter: parent admin menu slug for the Backup / Restore submenu.
	 *
	 * @var string
	 */
	public const FILTER_PARENT_MENU_SLUG = 'asc_ai_boiler_admin_parent_menu_slug';

	/**
	 * Filter: back-link label when Backup / Restore is mounted under a product menu.
	 *
	 * @var string
	 */
	public const FILTER_PARENT_MENU_LABEL = 'asc_ai_boiler_admin_parent_menu_label';

	/**
	 * Admin-post action for saving Backup / Restore checkboxes.
	 *
	 * @var string
	 */
	public const SAVE_FORM_ACTION = 'asc_ai_boiler_save_settings';

	/**
	 * Nonce action for saving Backup / Restore settings.
	 *
	 * @var string
	 */
	private const SAVE_SETTINGS_NONCE = 'asc_ai_boiler_save_settings_nonce';

	/**
	 * POST field: backup deletes orphan plugin files.
	 *
	 * @var string
	 */
	private const POST_BACKUP_CLEANUP = 'asc_ai_boiler_backup_cleanup';

	/**
	 * POST field: restore deletes orphan published content.
	 *
	 * @var string
	 */
	private const POST_RESTORE_CLEANUP = 'asc_ai_boiler_restore_cleanup';

	/**
	 * POST field: development mode (pre-check restore confirmation).
	 *
	 * @var string
	 */
	private const POST_DEVELOPMENT_MODE = 'asc_ai_boiler_development_mode';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'admin_post_' . self::SAVE_FORM_ACTION, array( $this, 'handle_save_sync_settings' ) );
		add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_RESTORE_BATCH, array( ContentSync::class, 'handle_ajax_restore_batch' ) );
		add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_BACKUP_BATCH, array( ContentSync::class, 'handle_ajax_backup_batch' ) );
		add_action( 'wp_ajax_' . ContentSync::AJAX_ACTION_DETECT_DIFFERENCES, array( ContentSync::class, 'handle_ajax_detect_differences' ) );
	}

	/**
	 * Register the Backup / Restore submenu under the parent slug from {@see self::parent_menu_slug()}.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		$parent_slug = self::parent_menu_slug();

		$hook_suffix = add_submenu_page(
			$parent_slug,
			__( 'Backup / Restore', \ASC_AI_BOILER_TEXT_DOMAIN ),
			__( 'Backup / Restore', \ASC_AI_BOILER_TEXT_DOMAIN ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_backup_restore_page' )
		);

		if ( is_string( $hook_suffix ) ) {
			self::$admin_hook_suffix = $hook_suffix;
		}
	}

	/**
	 * Hook suffix for the Backup / Restore screen (for {@see enqueue_admin_assets()}).
	 *
	 * @return string
	 */
	public static function admin_hook_suffix(): string {
		return self::$admin_hook_suffix;
	}

	/**
	 * Parent menu slug for this submenu.
	 *
	 * Defaults to WordPress Settings. Product layers may override via filter.
	 *
	 * @return string
	 */
	public static function parent_menu_slug(): string {
		$parent = apply_filters( self::FILTER_PARENT_MENU_SLUG, 'options-general.php' );
		if ( ! is_string( $parent ) ) {
			return 'options-general.php';
		}

		$parent = trim( $parent );
		if ( '' === $parent ) {
			return 'options-general.php';
		}

		return $parent;
	}

	/**
	 * Screen ID for this submenu page.
	 *
	 * @return string
	 */
	public static function screen_id(): string {
		$parent_slug = self::parent_menu_slug();
		if ( 'options-general.php' === $parent_slug ) {
			return 'settings_page_' . self::PAGE_SLUG;
		}

		return $parent_slug . '_page_' . self::PAGE_SLUG;
	}

	/**
	 * Persist sync settings checkboxes.
	 *
	 * @return void
	 */
	public function handle_save_sync_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update settings.', \ASC_AI_BOILER_TEXT_DOMAIN ) );
		}

		check_admin_referer( self::SAVE_SETTINGS_NONCE );

		SyncConfig::set_backup_cleanup( isset( $_POST[ self::POST_BACKUP_CLEANUP ] ) );
		SyncConfig::set_restore_cleanup( isset( $_POST[ self::POST_RESTORE_CLEANUP ] ) );
		SyncConfig::set_development_mode( isset( $_POST[ self::POST_DEVELOPMENT_MODE ] ) );

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
	public function render_backup_restore_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$backup_delete_orphans = SyncConfig::is_backup_cleanup();
		$restore_cleanup = SyncConfig::is_restore_cleanup();
		$restore_dev_mode = SyncConfig::is_development_mode();
		$auto_confirm_attr = $restore_dev_mode ? '1' : '0';

		?>
		<div class="wrap asc-ai-boiler-settings-page">
			<h1><?php esc_html_e( 'Backup / Restore', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></h1>

			<p class="asc-ai-boiler-settings-page__hub-back">
				<a href="<?php echo esc_url( self::parent_menu_url() ); ?>"><?php echo esc_html( self::parent_menu_label() ); ?></a>
			</p>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Backup / Restore settings', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="asc-ai-boiler-settings-page__default-form asc-ai-boiler-settings-page__sync-settings-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_FORM_ACTION ); ?>">
				<?php wp_nonce_field( self::SAVE_SETTINGS_NONCE ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Backup cleanup', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-backup-cleanup">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_BACKUP_CLEANUP ); ?>"
										id="asc-ai-boiler-backup-cleanup"
										value="1"
										<?php checked( $backup_delete_orphans ); ?>
									>
									<?php esc_html_e( 'After backup, delete plugin content files that have no matching published WordPress content.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Use when WordPress content was removed on purpose.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Restore cleanup', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-restore-cleanup">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_RESTORE_CLEANUP ); ?>"
										id="asc-ai-boiler-restore-cleanup"
										value="1"
										<?php checked( $restore_cleanup ); ?>
									>
									<?php esc_html_e( 'After restore, delete published WordPress content that has no matching plugin content files.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Use when plugin backup files were removed on purpose.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Developer mode', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></th>
							<td>
								<label for="asc-ai-boiler-restore-development-mode">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::POST_DEVELOPMENT_MODE ); ?>"
										id="asc-ai-boiler-restore-development-mode"
										value="1"
										<?php checked( $restore_dev_mode ); ?>
									>
									<?php esc_html_e( 'Pre-check the restore confirmation below.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Use for one-click restore from new plugin files by having the confirmation checkbox always checked.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Backup / Restore settings', \ASC_AI_BOILER_TEXT_DOMAIN ) ); ?>
			</form>

			<hr>

			<div class="asc-ai-boiler-settings-page__sync" id="asc-ai-boiler-sync-block" data-asc-ai-boiler-restore-auto-confirm="<?php echo esc_attr( $auto_confirm_attr ); ?>">
				<p class="description">
					<?php esc_html_e( 'Synchronize WordPress published content with plugin content files under the content directory.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Backup writes all published pages, posts, partials, and custom post types from the WordPress database to the plugin content files, including publication and modification dates, page/post title, page/post slug, tags, and categories. Whether orphaned plugin files are removed afterward depends on the backup cleanup setting above.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Restore updates all published pages, posts, partials, and custom post types from the plugin content files to the WordPress database, using the manifest for publication time, page/post title, page/post slug, tags, and categories when applicable. Last modified time in WordPress is not taken from the manifest. When restore finishes, plugin HTML and content-manifest.json on disk are rewritten to canonical backup form from WordPress. Whether orphaned published WordPress content is removed from the WordPress database afterward depends on the restore cleanup setting above.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
				</p>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of items per AJAX batch */
							__( 'Each backup or restore run processes up to %d published posts or plugin files per request, so large sites stay within PHP time limits.', \ASC_AI_BOILER_TEXT_DOMAIN ),
							SyncConfig::CONTENT_SYNC_BATCH_SIZE
						)
					);
					?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Restore imports plugin HTML and content/media/ into WordPress (including the media library, default images, and featured images via manifest bindings). Other post metadata (for example Yoast SEO fields) is not synced. Configure that data separately on each WordPress instance when needed.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?>
				</p>
				<div class="asc-ai-boiler-diff-highlight" id="asc-ai-boiler-diff-highlight" aria-live="polite"></div>
				<p class="asc-ai-boiler-settings-page__sync-detect-wrap">
					<button type="button" class="button" id="asc-ai-boiler-detect-difference"><?php esc_html_e( 'Detect Differences', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></button>
				</p>
				<div class="asc-ai-boiler-sync-status" id="asc-ai-boiler-sync-status">
					<p class="description" id="asc-ai-boiler-sync-progress" aria-live="polite"></p>
					<div id="asc-ai-boiler-sync-messages" class="asc-ai-boiler-settings-page__sync-ajax-messages"></div>
				</div>

				<div class="asc-ai-boiler-sync-actions asc-ai-boiler-settings-page__sync-form">
					<?php if ( $restore_cleanup ) : ?>
						<p class="description"><?php esc_html_e( 'Restore cleanup is enabled: finishing restore may move WordPress posts to the trash when their plugin HTML file is missing.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
					<p>
						<label class="asc-ai-boiler-settings-page__sync-restore-confirm" for="asc-ai-boiler-restore-confirm">
							<input type="checkbox" id="asc-ai-boiler-restore-confirm" value="1"<?php checked( $restore_dev_mode ); ?>>
							<span class="asc-ai-boiler-settings-page__sync-restore-confirm-text"><?php esc_html_e( 'I understand that restore will overwrite post bodies where plugin file markup differs from WordPress.', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></span>
						</label>
					</p>
					<p class="asc-ai-boiler-sync-actions__restore">
						<button type="button" class="button button-secondary" id="asc-ai-boiler-restore-submit"><?php esc_html_e( 'Restore from plugin files', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></button>
					</p>
					<p class="asc-ai-boiler-sync-actions__backup">
						<button type="button" class="button button-primary" id="asc-ai-boiler-backup-submit"><?php esc_html_e( 'Backup to plugin files', \ASC_AI_BOILER_TEXT_DOMAIN ); ?></button>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * URL for the parent admin menu page.
	 *
	 * @return string
	 */
	private static function parent_menu_url(): string {
		$parent_slug = self::parent_menu_slug();
		if ( 'options-general.php' === $parent_slug ) {
			return admin_url( 'options-general.php' );
		}

		return admin_url( 'admin.php?page=' . rawurlencode( $parent_slug ) );
	}

	/**
	 * Label text for the backlink above this page.
	 *
	 * @return string
	 */
	private static function parent_menu_label(): string {
		$parent_slug = self::parent_menu_slug();
		if ( 'options-general.php' === $parent_slug ) {
			return __( 'Settings', \ASC_AI_BOILER_TEXT_DOMAIN );
		}

		$label = apply_filters( self::FILTER_PARENT_MENU_LABEL, __( 'Back', \ASC_AI_BOILER_TEXT_DOMAIN ) );
		if ( ! is_string( $label ) || '' === trim( $label ) ) {
			return __( 'Back', \ASC_AI_BOILER_TEXT_DOMAIN );
		}

		return $label;
	}
}
