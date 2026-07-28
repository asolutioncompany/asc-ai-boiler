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

final class ContentExporter {

	/**
	 * Export up to {@see SyncConfig::CONTENT_SYNC_BATCH_SIZE} published posts to disk.
	 * When all posts are processed, optionally deletes orphan plugin files (see settings) and rewrites content-manifest.json.
	 *
	 * @param int $type_index Index into the ordered type keys from {@see ContentSyncProfile::sync_types()}.
	 * @param int $post_offset Offset into the current type's published post list.
	 *
	 * @return array{
	 *   ok: bool,
	 *   done: bool,
	 *   type_index: int,
	 *   post_offset: int,
	 *   updated_in_batch: int,
	 *   manifest_metadata_refreshed_in_batch: int,
	 *   messages: list<string>
	 * }
	 */
	public static function run_export_batch( int $type_index, int $post_offset ): array {
		$type_entries = ContentSyncProfile::sync_types();
		$type_keys = array_keys( $type_entries );
		$num_types = count( $type_keys );
		$batch_size = SyncConfig::CONTENT_SYNC_BATCH_SIZE;

		$type_index = max( 0, $type_index );
		$post_offset = max( 0, $post_offset );

		if ( 0 === $type_index && 0 === $post_offset ) {
			ContentManifest::invalidate_content_manifest_cache();
		}

		$messages = array();
		$updated_in_batch = 0;
		$manifest_metadata_refreshed_in_batch = 0;
		$timestamp_touched_count = 0;
		$ti = $type_index;
		$po = $post_offset;
		$remaining = $batch_size;

		while ( $remaining > 0 && $ti < $num_types ) {
			$type_key = $type_keys[ $ti ];
			$type_config = $type_entries[ $type_key ];
			$posts = ContentSync::query_posts_for_type( $type_config['post_type'] );
			$count = count( $posts );

			if ( $po >= $count ) {
				$ti++;
				$po = 0;
				continue;
			}

			while ( $po < $count && $remaining > 0 ) {
				$post = $posts[ $po ];
				$po++;
				if ( ! ( $post instanceof WP_Post ) ) {
					$remaining--;
					continue;
				}

				$filename = ContentSync::derive_filename_for_post( $type_key, $post );
				if ( '' === $filename ) {
					$remaining--;
					continue;
				}

				$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );
				$markup = (string) $post->post_content;
				$absolute = ContentSync::get_content_type_directory( $type_key ) . $filename;
				$skip_write = is_file( $absolute )
					&& ContentSync::markup_is_in_sync( ContentSync::read_content_markup( $type_key, $filename ), $markup );
				if ( $skip_write ) {
					$normalized = self::maybe_normalize_plugin_file_on_disk( $type_key, $filename, wp_unslash( $markup ), $messages );
					if ( $normalized ) {
						++$updated_in_batch;
					}

					$ft = false;
					if ( is_file( $absolute ) ) {
						$ft = filemtime( $absolute );
					}
					$file_ts = 0;
					if ( false !== $ft ) {
						$file_ts = (int) $ft;
					}
					$wp_ts = (int) get_post_modified_time( 'U', true, $post );
					if ( $wp_ts > 0 && $file_ts !== $wp_ts && ! $normalized ) {
						touch( $absolute, $wp_ts );
						++$timestamp_touched_count;
					}

					if ( ContentManifest::export_manifest_row_differs_from_post( $type_key, $filename, $post ) ) {
						++$manifest_metadata_refreshed_in_batch;
						$messages[] = sprintf(
							/* translators: %s: relative plugin path */
							__( 'Manifest metadata will refresh for %s (HTML already matched on disk).', \ASC_AI_PLUGIN_DOMAIN ),
							$relative_path
						);
					}

					if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
						CompanionFileSync::export_companion_files_for_post( $post, $filename );
					}

					$remaining--;
					continue;
				}

				$saved = ContentSync::write_content_markup( $type_key, $filename, $markup );
				if ( $saved ) {
					$wp_ts = (int) get_post_modified_time( 'U', true, $post );
					if ( $wp_ts > 0 ) {
						touch( $absolute, $wp_ts );
					}
					++$updated_in_batch;
					$exported_line = __( 'Exported %s.', \ASC_AI_PLUGIN_DOMAIN );
					$messages[] = sprintf( $exported_line, $relative_path );
				} else {
					$messages[] = sprintf(
						/* translators: %s: relative path */
						__( 'Failed to write %s. Check file permissions.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
					);
				}

				if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
					CompanionFileSync::export_companion_files_for_post( $post, $filename );
				}

				$remaining--;
			}

			if ( $po >= $count ) {
				$ti++;
				$po = 0;
			}
		}

		$done = $ti >= $num_types;
		if ( $done && $num_types > 0 ) {
			if ( SyncConfig::is_export_cleanup() ) {
				self::delete_orphan_plugin_files( $messages );
			}
			self::maybe_export_plugin_media( $messages );
			ContentManifest::write_content_export_manifest( $messages );
		}

		if ( $timestamp_touched_count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of files */
				_n(
					'Synchronized file modification timestamp on disk for %d file.',
					'Synchronized file modification timestamps on disk for %d files.',
					$timestamp_touched_count,
					\ASC_AI_PLUGIN_DOMAIN
				),
				$timestamp_touched_count
			);
		}

		return array(
			'ok' => true,
			'done' => $done,
			'type_index' => $ti,
			'post_offset' => $po,
			'updated_in_batch' => $updated_in_batch,
			'manifest_metadata_refreshed_in_batch' => $manifest_metadata_refreshed_in_batch,
			'messages' => $messages,
			'summary' => ContentSync::get_sync_summary_totals(),
		);
	}

	/**
	 * Export all published WordPress content to plugin files, rewrite content-manifest.json from the
	 * database, and delete plugin HTML with no matching published content.
	 *
	 * Uses the same batch stepping as the Import / Export admin AJAX export action.
	 *
	 * @return array{ok:bool, messages:list<string>, updated:int, manifest_metadata_refreshed:int}
	 */
	public static function export_to_files(): array {
		$messages = array();
		$updated = 0;
		$manifest_metadata_refreshed = 0;
		$type_index = 0;
		$post_offset = 0;
		$done = false;

		while ( ! $done ) {
			$batch = self::run_export_batch( $type_index, $post_offset );
			if ( ! $batch['ok'] ) {
				return array(
					'ok' => false,
					'messages' => array_merge( $messages, $batch['messages'] ),
					'updated' => $updated,
					'manifest_metadata_refreshed' => $manifest_metadata_refreshed,
				);
			}

			$messages = array_merge( $messages, $batch['messages'] );
			$updated += $batch['updated_in_batch'];
			$manifest_metadata_refreshed += $batch['manifest_metadata_refreshed_in_batch'];
			$type_index = $batch['type_index'];
			$post_offset = $batch['post_offset'];
			$done = $batch['done'];
		}

		return array(
			'ok' => true,
			'messages' => $messages,
			'updated' => $updated,
			'manifest_metadata_refreshed' => $manifest_metadata_refreshed,
		);
	}

	/**
	 * Rewrite on-disk plugin HTML to canonical export form when raw bytes differ (BOM, CRLF, outer trim).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename HTML basename.
	 * @param string $markup_source Markup to persist; {@see normalize_markup_for_storage()} is applied before write.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool True when the file was rewritten.
	 */
	public static function maybe_normalize_plugin_file_on_disk(
		string $type_key,
		string $filename,
		string $markup_source,
		array &$messages
	): bool {
		$absolute = ContentSync::resolve_content_file_path( $type_key, $filename );
		if ( ! is_readable( $absolute ) ) {
			return false;
		}

		$raw_disk = (string) file_get_contents( $absolute );
		$canonical = ContentSync::normalize_markup_for_storage( wp_unslash( $markup_source ) );
		if ( $raw_disk === $canonical ) {
			return false;
		}

		$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );
		if ( ! ContentSync::write_content_markup( $type_key, $filename, $markup_source ) ) {
			$messages[] = sprintf(
				/* translators: %s: relative path */
				__( 'Failed to normalize %s. Check file permissions.', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path
			);

			return false;
		}

		$messages[] = sprintf(
			/* translators: %s: relative plugin path */
			__( 'Normalized plugin file %s to canonical export form.', \ASC_AI_PLUGIN_DOMAIN ),
			$relative_path
		);

		return true;
	}

	/**
	 * True when the on-disk file is not byte-identical to the canonical export form (line endings, BOM, outer trim)
	 * while still matching WordPress after normalized comparison.
	 *
	 * @param string $absolute Absolute path to the plugin HTML file.
	 * @param string $post_content Post content as from {@see WP_Post::$post_content}.
	 *
	 * @return bool
	 */
	public static function plugin_file_needs_whitespace_normalization( string $absolute, string $post_content ): bool {
		if ( ! is_readable( $absolute ) ) {
			return false;
		}

		$raw = (string) file_get_contents( $absolute );
		$canonical = ContentSync::normalize_markup_for_storage( wp_unslash( $post_content ) );

		return $raw !== $canonical;
	}

	/**
	 * Delete plugin HTML files that have no matching published post or page.
	 *
	 * @param list<string> $messages Accumulated log lines.
	 *
	 * @return int Number of files deleted.
	 */
	public static function delete_orphan_plugin_files( array &$messages ): int {
		$orphans = self::collect_orphan_plugin_files();
		if ( array() === $orphans ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $orphans as $row ) {
			$type_key = (string) $row['type'];
			$filename = (string) $row['filename'];
			$relative_path = (string) $row['relative_path'];

			if ( ContentSync::delete_content_file( $type_key, $filename ) ) {
				++$deleted;
				$line = __( 'Deleted orphan content file %s.', \ASC_AI_PLUGIN_DOMAIN );
				$messages[] = sprintf( $line, $relative_path );
			} else {
				$fail_line = __( 'Could not delete orphan content file %s.', \ASC_AI_PLUGIN_DOMAIN );
				$messages[] = sprintf( $fail_line, $relative_path );
			}

			CompanionFileSync::delete_companion_text_file( SyncConfig::CONTENT_DIR_EXCERPTS, $filename );
			CompanionFileSync::delete_companion_text_file( SyncConfig::CONTENT_DIR_META_DESCRIPTIONS, $filename );
			CompanionFileSync::delete_companion_text_file( SyncConfig::CONTENT_DIR_SOCIAL_DESCRIPTIONS, $filename );
			CompanionFileSync::delete_companion_text_file( SyncConfig::CONTENT_DIR_X_DESCRIPTIONS, $filename );
		}

		if ( $deleted > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of deleted files */
				_n(
					'Deleted %d orphan plugin file.',
					'Deleted %d orphan plugin files.',
					$deleted,
					\ASC_AI_PLUGIN_DOMAIN
				),
				$deleted
			);
		}

		return $deleted;
	}

	/**
	 * Plugin files on disk with no matching published post (or page / partial shell).
	 *
	 * @return list<array{type:string, filename:string, relative_path:string}>
	 */
	public static function collect_orphan_plugin_files(): array {
		$out = array();
		foreach ( array_keys( ContentSyncProfile::sync_types() ) as $type_key ) {
			foreach ( ContentSync::list_content_files( $type_key ) as $filename ) {
				$post = ContentSync::find_post_for_filename( $type_key, $filename );
				if ( null !== $post ) {
					continue;
				}
				$out[] = array(
					'type' => $type_key,
					'filename' => $filename,
					'relative_path' => ContentSync::relative_content_type_file_path( $type_key, $filename ),
				);
			}
		}

		return $out;
	}

	/**
	 * Export bound attachments from WordPress to content/media/.
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return void
	 */
	public static function maybe_export_plugin_media( array &$messages ): void {
		if ( ! SyncConfig::is_media_sync_enabled() ) {
			return;
		}
		ContentMediaSync::export_to_plugin_files( $messages );
	}

	/**
	 * Published synced posts whose derived on-disk filename does not exist under the content directory.
	 *
	 * @return list<array{post_id:int, type_key:string, filename:string, relative_path:string}>
	 */
	public static function collect_orphan_wordpress_posts(): array {
		$out = array();
		foreach ( ContentSyncProfile::sync_types() as $type_key => $type_config ) {
			$post_type = (string) $type_config['post_type'];
			foreach ( ContentSync::query_posts_for_type( $post_type ) as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$filename = ContentSync::derive_filename_for_post( $type_key, $post );
				if ( '' === $filename ) {
					continue;
				}

				$absolute = ContentSync::get_content_type_directory( $type_key ) . $filename;
				if ( is_file( $absolute ) ) {
					continue;
				}

				$out[] = array(
					'post_id' => (int) $post->ID,
					'type_key' => $type_key,
					'filename' => $filename,
					'relative_path' => ContentSync::relative_content_type_file_path( $type_key, $filename ),
				);
			}
		}

		return $out;
	}

}
