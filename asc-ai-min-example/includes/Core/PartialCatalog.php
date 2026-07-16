<?php
/**
 * Minimum example reserved partial keys.
 *
 * @package asc-ai-min-example
 */

declare( strict_types = 1 );

namespace ASC\AI_MIN_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product-layer partial shell catalog.
 */
final class PartialCatalog {

	public const KEY_HEADER = 'header';

	public const KEY_FOOTER = 'footer';

	public const KEY_CONTACT_CALL_TO_ACTION = 'contact_call_to_action';

	public const KEY_AGENCY_BOILER = 'agency_boiler';

	public const KEY_BLOG_BOILER = 'blog_boiler';

	public const KEY_SOCIAL_LINKS = 'social_links';
}
