<?php
/**
 * Example overrides for boiler static content sync profile (partial shell filename map).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\ExampleCore;

use ASC\AI_BOILER\Admin\ContentSyncProfile;
use ASC\AI_BOILER\ExampleCore\RegisterProjects;
use ASC\AI_BOILER\ExampleCore\RegisterServices;

/**
 * Product partial shell map and profile filter.
 */
final class ExampleContentSyncProfile {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( ContentSyncProfile::FILTER_PROFILE, array( self::class, 'filter_profile' ), 10, 1 );
	}

	/**
	 * Filename => logical partial key for Example shell HTML under content/partials/.
	 *
	 * @return array<string, string>
	 */
	public static function partial_shell_map(): array {
		return array(
			'header.html' => ExamplePartialCatalog::KEY_HEADER,
			'footer.html' => ExamplePartialCatalog::KEY_FOOTER,
			CoreSettings::CONTACT_CALL_TO_ACTION_PARTIAL_FILE => ExamplePartialCatalog::KEY_CONTACT_CALL_TO_ACTION,
			CoreSettings::AGENCY_BOILERPLATE_PARTIAL_FILE => ExamplePartialCatalog::KEY_AGENCY_BOILER,
			CoreSettings::BLOG_BOILERPLATE_PARTIAL_FILE => ExamplePartialCatalog::KEY_BLOG_BOILER,
			'blog-boilerplate.html' => ExamplePartialCatalog::KEY_BLOG_BOILER,
			CoreSettings::SOCIAL_LINKS_PARTIAL_FILE => ExamplePartialCatalog::KEY_SOCIAL_LINKS,
		);
	}

	/**
	 * @param array{
	 *   sync_types: array<string, array{post_type:string, label:string}>,
	 *   cpt_shell_map: array<string, string>,
	 *   page_body_map: array<string, array{type:string, slug?:string, title:string}>
	 * } $profile Profile defaults from manifest.
	 *
	 * @return array{
	 *   sync_types: array<string, array{post_type:string, label:string}>,
	 *   cpt_shell_map: array<string, string>,
	 *   page_body_map: array<string, array{type:string, slug?:string, title:string}>
	 * }
	 */
	public static function filter_profile( array $profile ): array {
		$profile['cpt_shell_map'] = array_merge( $profile['cpt_shell_map'], self::partial_shell_map() );
		$profile['sync_types'] = array_merge(
			$profile['sync_types'],
			array(
				CoreSettings::CONTENT_TYPE_SERVICES => array(
					'post_type' => RegisterServices::POST_TYPE,
					'label' => __( 'Services', \ASC_AI_BOILER_TEXT_DOMAIN ),
				),
				CoreSettings::CONTENT_TYPE_PROJECTS => array(
					'post_type' => RegisterProjects::POST_TYPE,
					'label' => __( 'Projects', \ASC_AI_BOILER_TEXT_DOMAIN ),
				),
			)
		);
		return $profile;
	}
}
