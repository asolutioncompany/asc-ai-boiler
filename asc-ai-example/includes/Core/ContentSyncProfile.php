<?php
/**
 * Minimum overrides for boiler static content sync profile (partial shell filename map).
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product partial shell map and profile filter.
 */
final class ContentSyncProfile {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'asc_ai_boiler_content_sync_profile', array( self::class, 'filter_profile' ), 10, 1 );
	}

	/**
	 * Filename => logical partial key for Example shell HTML under content/partials/.
	 *
	 * @return array<string, string>
	 */
	public static function partial_shell_map(): array {
		return array(
			'header.html' => PartialCatalog::KEY_HEADER,
			'footer.html' => PartialCatalog::KEY_FOOTER,
			CoreSettings::CONTACT_CALL_TO_ACTION_PARTIAL_FILE => PartialCatalog::KEY_CONTACT_CALL_TO_ACTION,
			CoreSettings::AGENCY_BOILERPLATE_PARTIAL_FILE => PartialCatalog::KEY_AGENCY_BOILER,
			CoreSettings::BLOG_BOILERPLATE_PARTIAL_FILE => PartialCatalog::KEY_BLOG_BOILER,
			'blog-boilerplate.html' => PartialCatalog::KEY_BLOG_BOILER,
			CoreSettings::SOCIAL_LINKS_PARTIAL_FILE => PartialCatalog::KEY_SOCIAL_LINKS,
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
				CoreSettings::CONTENT_TYPE_PORTFOLIO => array(
					'post_type' => RegisterPortfolio::POST_TYPE,
					'label' => __( 'Portfolio', 'asc-ai-example' ),
				),
			)
		);
		return $profile;
	}
}
