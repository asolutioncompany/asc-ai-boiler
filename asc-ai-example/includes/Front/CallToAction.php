<?php
/**
 * Call-to-action shortcodes: markup loaded from the Contact Call to Action partial (Partials CPT).
 *
 * Shortcode registration lives in RegisterShortcodes.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Core\PartialStore;
use ASC\AI_EXAMPLE\Core\PartialCatalog;

/**
 * CTA shortcode handlers (content from partial; {@see PartialCatalog::KEY_CONTACT_CALL_TO_ACTION}).
 */
class CallToAction {

	/**
	 * Home page Request Quote CTA (Contact Call to Action partial).
	 *
	 * Shortcode: `[example_home_cta_request_quote]`.
	 *
	 * @return string
	 */
	public function render_home_cta_request_quote_shortcode(): string {
		return $this->render_cta_shortcode();
	}

	/**
	 * Contact call-to-action partial (all pages before the footer).
	 *
	 * Shortcode: `[example_cta]`.
	 *
	 * @return string
	 */
	public function render_cta_shortcode(): string {
		$markup = PartialStore::get_raw_markup( PartialCatalog::KEY_CONTACT_CALL_TO_ACTION );
		if ( '' === trim( $markup ) ) {
			return '';
		}

		return do_shortcode( $markup );
	}
}
