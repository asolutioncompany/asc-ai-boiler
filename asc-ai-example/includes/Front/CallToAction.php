<?php
/**
 * Call-to-action shortcodes.
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_EXAMPLE\Core\PartialCatalog;
use ASC\AI_EXAMPLE\Core\PartialStore;

/**
 * CTA shortcode handlers.
 */
class CallToAction {

	/**
	 * Home page Request Quote CTA.
	 *
	 * @return string
	 */
	public function render_home_cta_request_quote_shortcode(): string {
		return $this->render_cta_shortcode();
	}

	/**
	 * Contact call-to-action partial (all pages before the footer).
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
