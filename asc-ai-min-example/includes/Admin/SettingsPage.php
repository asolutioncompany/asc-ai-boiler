<?php
/**
 * Minimum Example Settings: default images and quick links to content admin screens.
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
use ASC\AI_MIN_EXAMPLE\Core\CoreSettings;

/**
 * Top-level Minimum Example Settings admin page.
 */
class SettingsPage {

	/**
	 * Admin page slug (menu slug).
	 *
	 * @var string
	 */
	public const PAGE_SLUG = CoreSettings::ADMIN_SETTINGS_PAGE_SLUG;

	/**
	 * Admin-post action for default images.
	 *
	 * @var string
	 */
	private const SAVE_ACTION = 'min_example_site_save_settings';

	private const AJAX_SET_IMAGE_ACTION = 'min_example_site_set_default_image';

	private const TASK_SAVE_SETTINGS = 'save_settings';

	/**
	 * Nonce action for settings save.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'min_example_site_settings_save';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_' . self::AJAX_SET_IMAGE_ACTION, array( $this, 'handle_ajax_set_default_image' ) );
	}

	/**
	 * Register top-level menu and first submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'aS.c Min Example Settings', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
			__( 'aS.c Min Example', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-admin-site-alt3',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'aS.c Min Example Settings', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
			__( 'aS.c Min Example Settings', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Save image settings only.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update settings.', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$task = self::TASK_SAVE_SETTINGS;
		if ( isset( $_POST['min_example_settings_task'] ) ) {
			$task = sanitize_key( (string) wp_unslash( $_POST['min_example_settings_task'] ) );
		}

		if ( self::TASK_SAVE_SETTINGS !== $task ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			exit;
		}

		$image_settings_input = array();
		if ( isset( $_POST[ CoreSettings::OPTION_KEY ] ) ) {
			$image_settings_input = wp_unslash( $_POST[ CoreSettings::OPTION_KEY ] );
		}
		$sanitized_image_settings = CoreSettings::sanitize_image_settings_input( $image_settings_input );
		update_option( CoreSettings::OPTION_KEY, $sanitized_image_settings );

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
	 * Render Settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = CoreSettings::get_settings();
		$image_fields = $this->get_image_fields();
		$content_links = $this->get_content_admin_links();

		?>
		<div class="wrap example-settings-page example-admin-settings-hub">
			<h1><?php esc_html_e( 'aS.c Minimum Example Settings', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?></h1>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Content', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?></h2>
			<ul class="example-admin-settings-hub__links">
				<?php foreach ( $content_links as $link ) : ?>
					<li>
						<a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<hr>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="example-settings-page__default-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="min_example_settings_task" value="<?php echo esc_attr( self::TASK_SAVE_SETTINGS ); ?>">

				<h2><?php esc_html_e( 'Default Images', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?></h2>
				<p>
					<?php esc_html_e( 'Images should have a 4:3 aspect ratio. Recommended image size: 1440x1080. Setting the alt text description is advised for selected images.', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $image_fields as $field ) : ?>
							<?php
							$key = $field['key'];
							$attachment_id = 0;
							if ( isset( $settings[ $key ] ) ) {
								$attachment_id = (int) $settings[ $key ];
							}
							$image_url = '';
							if ( $attachment_id > 0 ) {
								$url = wp_get_attachment_image_url( $attachment_id, 'medium' );
								if ( is_string( $url ) ) {
									$image_url = $url;
								}
							}
							?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
								</th>
								<td>
									<input
										type="hidden"
										id="<?php echo esc_attr( $key ); ?>"
										name="<?php echo esc_attr( CoreSettings::OPTION_KEY . '[' . $key . ']' ); ?>"
										value="<?php echo esc_attr( (string) $attachment_id ); ?>"
										class="example-settings-media-id"
									>
									<button
										type="button"
										class="button example-settings-media-select"
										data-target-input="<?php echo esc_attr( $key ); ?>"
									>
										<?php esc_html_e( 'Select Image', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?>
									</button>
									<button
										type="button"
										class="button example-settings-media-clear"
										data-target-input="<?php echo esc_attr( $key ); ?>"
									>
										<?php esc_html_e( 'Clear', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'Attachment ID:', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ); ?>
										<span class="example-settings-media-id-text"><?php echo esc_html( (string) $attachment_id ); ?></span>
									</p>
									<div class="example-settings-media-preview-wrap">
										<?php if ( '' !== $image_url ) : ?>
											<p><img class="example-settings-media-preview" src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width: 260px; height: auto;"></p>
										<?php else : ?>
											<p><img class="example-settings-media-preview" src="" alt="" style="max-width: 260px; height: auto; display: none;"></p>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save default images', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Admin list/edit links for synced content types.
	 *
	 * @return list<array{url:string, label:string}>
	 */
	private function get_content_admin_links(): array {
		$out = array();

		$out[] = array(
			'url' => admin_url( 'edit.php?post_type=page' ),
			'label' => __( 'Pages', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
		);

		$out[] = array(
			'url' => admin_url( 'edit.php' ),
			'label' => __( 'Posts', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
		);

		$partial_obj = get_post_type_object( RegisterPartials::POST_TYPE );
		if ( $partial_obj instanceof \WP_Post_Type ) {
			$out[] = array(
				'url' => admin_url( 'edit.php?post_type=' . RegisterPartials::POST_TYPE ),
				'label' => $partial_obj->labels->name,
			);
		}

		return $out;
	}

	/**
	 * Image field config for settings page.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_image_fields(): array {
		return array(
			array(
				'key' => CoreSettings::SETTING_IMAGE_BLOG_DEFAULT,
				'label' => __( 'Blog default image', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * AJAX: set one default image setting value.
	 *
	 * @return void
	 */
	public function handle_ajax_set_default_image(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to update settings.', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
				),
				403
			);
		}

		check_ajax_referer( 'asc-ai-boiler-admin-ajax-nonce' );

		$setting_key = '';
		if ( isset( $_POST['setting_key'] ) ) {
			$setting_key = sanitize_key( (string) wp_unslash( $_POST['setting_key'] ) );
		}
		$attachment_id = 0;
		if ( isset( $_POST['attachment_id'] ) ) {
			$attachment_id = absint( wp_unslash( $_POST['attachment_id'] ) );
		}

		$allowed_keys = CoreSettings::get_image_setting_keys();
		if ( ! in_array( $setting_key, $allowed_keys, true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid settings key.', \ASC_AI_MIN_EXAMPLE_TEXT_DOMAIN ),
				),
				400
			);
		}

		$settings = CoreSettings::get_settings();
		$settings[ $setting_key ] = $attachment_id;
		update_option( CoreSettings::OPTION_KEY, $settings );

		$preview_url = '';
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'medium' );
			if ( is_string( $url ) ) {
				$preview_url = $url;
			}
		}

		wp_send_json_success(
			array(
				'setting_key' => $setting_key,
				'attachment_id' => $attachment_id,
				'preview_url' => $preview_url,
			)
		);
	}
}
