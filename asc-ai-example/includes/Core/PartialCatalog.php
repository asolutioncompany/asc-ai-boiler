<?php
/**
 * Example reserved partial keys (logical values for {@see \ASC\AI_BOILER\Core\RegisterPartials::META_PARTIAL_KEY}).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product-layer partial shell catalog (keys map to {@see \ASC\AI_BOILER\Core\RegisterPartials::META_PARTIAL_KEY} values).
 */
final class PartialCatalog {

	public const KEY_HEADER = 'header';

	public const KEY_FOOTER = 'footer';

	public const KEY_CONTACT_CALL_TO_ACTION = 'contact_call_to_action';

	public const KEY_AGENCY_BOILER = 'agency_boiler';

	public const KEY_BLOG_BOILER = 'blog_boiler';

	public const KEY_SOCIAL_LINKS = 'social_links';
}
