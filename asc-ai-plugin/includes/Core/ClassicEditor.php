<?php
/**
 * Classic editor by default for all post types (block editor opt-in per type via filter).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Core;

/**
 * Disables the block editor unless a product layer opts a post type back in.
 */
final class ClassicEditor {

	/**
	 * Filter: return true to allow the block editor for the given post type.
	 *
	 * Default is false (classic editor for every post type).
	 *
	 * @var string
	 */
	public const FILTER_ALLOW_BLOCK_EDITOR = 'asc_ai_boiler_allow_block_editor_for_post_type';

	/**
	 * @return void
	 */
	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'use_block_editor_for_post_type', array( self::class, 'filter_use_block_editor_for_post_type' ), 10, 2 );
		add_filter( 'use_block_editor_for_post', array( self::class, 'filter_use_block_editor_for_post' ), 10, 2 );
	}

	/**
	 * @param bool   $use Whether WordPress would use the block editor.
	 * @param string $post_type Post type key.
	 *
	 * @return bool
	 */
	public static function filter_use_block_editor_for_post_type( bool $use, string $post_type ): bool {
		$allow_block = apply_filters( self::FILTER_ALLOW_BLOCK_EDITOR, false, $post_type );
		if ( $allow_block ) {
			return true;
		}

		return false;
	}

	/**
	 * @param bool     $use Whether WordPress would use the block editor.
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool
	 */
	public static function filter_use_block_editor_for_post( bool $use, \WP_Post $post ): bool {
		$allow_block = apply_filters( self::FILTER_ALLOW_BLOCK_EDITOR, false, $post->post_type );
		if ( $allow_block ) {
			return true;
		}

		return false;
	}
}
