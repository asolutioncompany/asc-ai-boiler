<?php
/**
 * Registers Example-layer hooks into boiler Admin and Core (no boiler imports of Example classes).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleCore;

use ASC\AI_BOILER\Admin\ContentSync;
use ASC\AI_BOILER\Admin\SettingsPage as BoilerSettingsPage;
use ASC\AI_BOILER\Core\RegisterPartials;

/**
 * Example product integration with aS.c AI Boiler core APIs.
 */
final class BoilerIntegration {

	/**
	 * Wire Example sync profile, content types, and admin menu placement into boiler hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		ExampleContentSyncProfile::register();
		ExampleMediaBindings::register();
		ExampleThemeShell::register();
		add_filter( ContentSync::FILTER_SYNC_CONTENT_TYPE_KEYS, array( self::class, 'append_sync_content_type_keys' ), 10, 1 );
		add_filter( BoilerSettingsPage::FILTER_PARENT_MENU_SLUG, array( self::class, 'filter_boiler_parent_menu_slug' ) );
		add_filter( BoilerSettingsPage::FILTER_PARENT_MENU_LABEL, array( self::class, 'filter_boiler_parent_menu_label' ) );
		add_filter( RegisterPartials::FILTER_ADMIN_MENU_PARENT, array( self::class, 'filter_partial_admin_menu_parent' ) );
	}

	/**
	 * @param list<string> $keys Built-in content sync type keys from boiler.
	 *
	 * @return list<string>
	 */
	public static function append_sync_content_type_keys( array $keys ): array {
		return array_merge(
			$keys,
			array(
				CoreSettings::CONTENT_TYPE_SERVICES,
				CoreSettings::CONTENT_TYPE_PROJECTS,
			)
		);
	}

	/**
	 * Mount boiler Backup / Restore under Boiler Settings.
	 *
	 * @param string $parent_slug Default parent menu slug from boiler.
	 *
	 * @return string
	 */
	public static function filter_boiler_parent_menu_slug( string $parent_slug ): string {
		return CoreSettings::ADMIN_SETTINGS_PAGE_SLUG;
	}

	/**
	 * Back-link label on the boiler Backup / Restore screen.
	 *
	 * @param string $label Default label from boiler.
	 *
	 * @return string
	 */
	public static function filter_boiler_parent_menu_label( string $label ): string {
		return __( 'Boiler Settings', \ASC_AI_BOILER_TEXT_DOMAIN );
	}

	/**
	 * Place Partials under Boiler Settings instead of a top-level menu.
	 *
	 * @param bool|string $parent Default from boiler ({@see RegisterPartials::register()}).
	 *
	 * @return string
	 */
	public static function filter_partial_admin_menu_parent( bool|string $parent ): string {
		return CoreSettings::ADMIN_SETTINGS_PAGE_SLUG;
	}
}
