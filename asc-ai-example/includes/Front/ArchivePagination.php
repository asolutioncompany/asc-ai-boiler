<?php
/**
 * Pagination markup for paged archive shortcodes.
 *
 * @package asc-ai-example
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prev / Next + Page X of N for static pages using the paged query var.
 */
class ArchivePagination {

	public static function get_current_paged(): int {
		$p = (int) get_query_var( 'paged' );
		if ( $p > 0 ) {
			return $p;
		}

		$p = (int) get_query_var( 'page' );

		return max( 1, $p );
	}

	public static function paged_url( string $permalink, int $page ): string {
		$page = max( 1, $page );
		if ( 1 === $page ) {
			return $permalink;
		}

		return add_query_arg( 'paged', $page, $permalink );
	}

	public static function render( int $current_page, int $total_pages, string $permalink ): string {
		$total_pages = max( 1, $total_pages );
		$current_page = min( max( 1, $current_page ), $total_pages );

		$prev_url = '';
		$next_url = '';
		if ( $current_page > 1 ) {
			$prev_url = self::paged_url( $permalink, $current_page - 1 );
		}
		if ( $current_page < $total_pages ) {
			$next_url = self::paged_url( $permalink, $current_page + 1 );
		}

		$prev_markup = '';
		if ( '' === $prev_url ) {
			$prev_markup = '<span class="example-button-blue example-listing-pagination-prev example-listing-pagination-link--disabled" aria-disabled="true">'
				. '← '
				. esc_html__( 'Previous', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
				. '</span>';
		} else {
			$prev_markup = '<a class="example-button-blue example-listing-pagination-prev" href="' . esc_url( $prev_url ) . '">'
				. '← '
				. esc_html__( 'Previous', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
				. '</a>';
		}

		$next_markup = '';
		if ( '' === $next_url ) {
			$next_markup = '<span class="example-button-blue example-listing-pagination-next example-listing-pagination-link--disabled" aria-disabled="true">'
				. esc_html__( 'Next', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
				. ' →'
				. '</span>';
		} else {
			$next_markup = '<a class="example-button-blue example-listing-pagination-next" href="' . esc_url( $next_url ) . '">'
				. esc_html__( 'Next', \ASC_AI_EXAMPLE_TEXT_DOMAIN )
				. ' →'
				. '</a>';
		}

		$label = sprintf(
			esc_html__( 'Page %1$d of %2$d', \ASC_AI_EXAMPLE_TEXT_DOMAIN ),
			$current_page,
			$total_pages
		);

		return '<nav class="example-listing-pagination" aria-label="' . esc_attr__( 'Pagination', \ASC_AI_EXAMPLE_TEXT_DOMAIN ) . '">'
			. '<div class="example-listing-pagination-inner">'
			. $prev_markup
			. '<span class="example-listing-pagination-status">' . esc_html( $label ) . '</span>'
			. $next_markup
			. '</div>'
			. '</nav>';
	}
}
