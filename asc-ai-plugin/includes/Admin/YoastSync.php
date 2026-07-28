<?php
/**
 * Decomposed class.
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WP_Post;
use WP_Query;
use ASC\AI_BOILER\Core\Core;

final class YoastSync {

	/**
	 * Sync Yoast SEO social media images and descriptions with featured images and meta descriptions.
	 *
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return void
	 */
	public static function sync_all_yoast_social_meta( array &$messages ): void {
		if ( ! SyncConfig::is_yoast_sync() ) {
			return;
		}

		$sync_types = ContentSyncProfile::sync_types();

		foreach ( $sync_types as $type_key => $type_config ) {
			$post_type = (string) ( $type_config['post_type'] ?? '' );
			if ( '' === $post_type || SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				continue;
			}

			$posts = ContentSync::query_posts_for_type( $post_type );
			foreach ( $posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$post_id = (int) $post->ID;
				$featured_id = (int) get_post_thumbnail_id( $post_id );
				$changed = false;
				$details = array();

				if ( $featured_id > 0 ) {
					$featured_url = wp_get_attachment_url( $featured_id );
					if ( is_string( $featured_url ) && '' !== $featured_url ) {
						$og_id = (int) CompanionFileSync::get_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-image-id' );
						if ( $og_id !== $featured_id ) {
							CompanionFileSync::update_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-image-id', $featured_id );
							$changed = true;
							$details[] = "og_image_id ($og_id vs $featured_id)";
						}
						$og_url = trim( CompanionFileSync::get_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-image' ) );
						if ( $og_url !== $featured_url ) {
							CompanionFileSync::update_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-image', $featured_url );
							$changed = true;
							$details[] = "og_image_url ('$og_url' vs '$featured_url')";
						}

						$tw_id = (int) CompanionFileSync::get_post_meta_raw( $post_id, '_yoast_wpseo_twitter-image-id' );
						if ( $tw_id !== $featured_id ) {
							CompanionFileSync::update_post_meta_raw( $post_id, '_yoast_wpseo_twitter-image-id', $featured_id );
							$changed = true;
							$details[] = "tw_image_id ($tw_id vs $featured_id)";
						}
						$tw_url = trim( CompanionFileSync::get_post_meta_raw( $post_id, '_yoast_wpseo_twitter-image' ) );
						if ( $tw_url !== $featured_url ) {
							CompanionFileSync::update_post_meta_raw( $post_id, '_yoast_wpseo_twitter-image', $featured_url );
							$changed = true;
							$details[] = "tw_image_url ('$tw_url' vs '$featured_url')";
						}
					}
				}

				if ( $changed ) {
					$messages[] = sprintf(
						/* translators: 1: post slug, 2: change details */
						__( 'Synced Yoast social media image for %1$s. Details: %2$s', \ASC_AI_PLUGIN_DOMAIN ),
						$post->post_name,
						implode( ', ', $details )
					);
				}
			}
		}
	}

}
