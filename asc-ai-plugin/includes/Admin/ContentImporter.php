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

final class ContentImporter {

	/**
	 * Process up to {@see SyncConfig::CONTENT_SYNC_BATCH_SIZE} import jobs starting at $offset.
	 *
	 * @param int $offset Index into the canonical job list.
	 * @param bool $confirmed User confirmed replace.
	 *
	 * @return array{
	 *   ok:bool,
	 *   done:bool,
	 *   next_offset:int,
	 *   updated_in_batch:int,
	 *   processed_in_batch:int,
	 *   total_jobs:int,
	 *   messages:list<string>,
	 *   summary?:array{pages_scanned:int, posts_scanned:int, images_scanned:int}
	 * }
	 */
	public static function run_import_batch( int $offset, bool $confirmed ): array {
		if ( ! $confirmed ) {
			return array(
				'ok' => false,
				'done' => true,
				'next_offset' => 0,
				'updated_in_batch' => 0,
				'processed_in_batch' => 0,
				'total_jobs' => 0,
				'messages' => array( __( 'Import was not confirmed.', \ASC_AI_PLUGIN_DOMAIN ) ),
				'summary' => ContentSync::get_sync_summary_totals(),
			);
		}

		$jobs = self::collect_import_file_jobs();
		$total_jobs = count( $jobs );
		$offset = max( 0, $offset );

		if ( 0 === $offset ) {
			ContentManifest::invalidate_content_manifest_cache();
			ContentSync::ensure_partial_posts_from_manifest();
		}

		if ( $offset >= $total_jobs ) {
			$messages = array();
			if ( $offset > 0 && $confirmed ) {
				self::maybe_run_import_cleanup( $confirmed, $messages );
				self::maybe_import_plugin_media( $messages );
				YoastSync::sync_all_yoast_social_meta( $messages );
				ContentManifest::maybe_normalize_content_manifest_from_wordpress( $messages );
			}

			return array(
				'ok' => true,
				'done' => true,
				'next_offset' => $total_jobs,
				'updated_in_batch' => 0,
				'processed_in_batch' => 0,
				'total_jobs' => $total_jobs,
				'messages' => $messages,
				'summary' => ContentSync::get_sync_summary_totals(),
			);
		}

		$batch_size = SyncConfig::CONTENT_SYNC_BATCH_SIZE;
		$messages = array();
		$updated_in_batch = 0;
		$processed_in_batch = 0;
		$end = min( $total_jobs, $offset + $batch_size );

		for ( $i = $offset; $i < $end; $i++ ) {
			$job = $jobs[ $i ];
			$type_key = (string) $job['type'];
			$filename = (string) $job['filename'];
			if ( self::import_one_file( $type_key, $filename, $messages ) ) {
				$updated_in_batch++;
			}
			$processed_in_batch++;
		}

		$next_offset = $offset + $processed_in_batch;

		$done = $next_offset >= $total_jobs;
		if ( $done && $confirmed && $total_jobs > 0 ) {
			self::maybe_run_import_cleanup( $confirmed, $messages );
			self::maybe_import_plugin_media( $messages );
			YoastSync::sync_all_yoast_social_meta( $messages );
			ContentManifest::maybe_normalize_content_manifest_from_wordpress( $messages );
		}

		return array(
			'ok' => true,
			'done' => $done,
			'next_offset' => $next_offset,
			'updated_in_batch' => $updated_in_batch,
			'processed_in_batch' => $processed_in_batch,
			'total_jobs' => $total_jobs,
			'messages' => $messages,
			'summary' => ContentSync::get_sync_summary_totals(),
		);
	}

	/**
	 * Import plugin files into WordPress. Updates post bodies when on-disk markup differs. Rewrites each scanned
	 * plugin HTML file on disk to canonical export form when raw bytes differ. Replaces tags, categories, and
	 * manifest publication time when a manifest row applies (rows without `date_gmt` stamp “now” except for posts
	 * already published before the import). When import finishes, regenerates content-manifest.json from WordPress.
	 * Optional import cleanup can remove posts with no on-disk file (see settings).
	 *
	 * Uses the same batch size as the Import / Export admin AJAX import action.
	 *
	 * @param bool $confirmed User confirmed replace.
	 *
	 * @return array{ok:bool, messages:list<string>, updated:int}
	 */
	public static function import_from_files( bool $confirmed ): array {
		if ( ! $confirmed ) {
			return array(
				'ok' => false,
				'messages' => array( __( 'Import was not confirmed.', \ASC_AI_PLUGIN_DOMAIN ) ),
				'updated' => 0,
			);
		}

		$messages = array();
		$updated = 0;
		$offset = 0;
		$done = false;

		while ( ! $done ) {
			$batch = self::run_import_batch( $offset, true );
			if ( ! $batch['ok'] ) {
				return array(
					'ok' => false,
					'messages' => array_merge( $messages, $batch['messages'] ),
					'updated' => $updated,
				);
			}

			$messages = array_merge( $messages, $batch['messages'] );
			$updated += $batch['updated_in_batch'];
			$offset = $batch['next_offset'];
			$done = $batch['done'];
		}

		return array(
			'ok' => true,
			'messages' => $messages,
			'updated' => $updated,
		);
	}

	/**
	 * Flat list of import jobs in canonical type + filename order.
	 *
	 * @return list<array{type:string, filename:string}>
	 */
	public static function collect_import_file_jobs(): array {
		$jobs = array();
		foreach ( ContentSyncProfile::sync_types() as $type_key => $_unused ) {
			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$filenames = ContentSync::collect_partial_filenames_from_manifest();
			} else {
				$filenames = ContentSync::list_content_files( $type_key );
			}

			foreach ( $filenames as $filename ) {
				if ( ! ContentSync::is_valid_content_filename( $filename ) ) {
					continue;
				}
				$jobs[] = array(
					'type' => $type_key,
					'filename' => $filename,
				);
			}
		}

		return $jobs;
	}

	/**
	 * Determine the WordPress post_type for a given (type_key, filename) job.
	 */
	public static function get_post_type_for_job( string $type_key, string $filename = '' ): string {
		$sync_types = ContentSyncProfile::sync_types();
		if ( isset( $sync_types[ $type_key ]['post_type'] ) ) {
			$pt = trim( (string) $sync_types[ $type_key ]['post_type'] );
			if ( '' !== $pt ) {
				return $pt;
			}
		}

		if ( '' !== $filename ) {
			$post = ContentSync::find_post_for_filename( $type_key, $filename, false );
			if ( $post instanceof WP_Post ) {
				return (string) $post->post_type;
			}
		}

		if ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
			return 'page';
		}
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return 'asc_boiler_partial';
		}

		return 'post';
	}

	/**
	 * Apply one on-disk file to WordPress when markup differs, apply manifest taxonomies and publication time when present.
	 *
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return bool True when WordPress post content, taxonomies, publication time, title, or slug were updated.
	 */
	public static function import_one_file( string $type_key, string $filename, array &$messages ): bool {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return self::import_one_partial_from_manifest( $filename, $messages );
		}

		$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );

		$absolute = ContentSync::resolve_content_file_path( $type_key, $filename );
		if ( ! is_file( $absolute ) ) {
			return false;
		}

		$markup = ContentSync::normalize_markup_for_storage( ContentSync::read_content_markup( $type_key, $filename ) );

		$post = ContentSync::find_post_for_filename( $type_key, $filename, false );
		$existed_before = $post instanceof WP_Post;
		if ( null === $post ) {
			$post = self::create_post_from_seed( $type_key, $filename );
		}
		if ( null === $post ) {
			$post = self::create_post_from_manifest( $type_key, $filename );
		}
		if ( null === $post ) {
			$post = self::create_post_minimal_from_disk( $type_key, $filename );
		}
		if ( null === $post ) {
			return false;
		}

		$post_id = (int) $post->ID;
		$post_type = (string) $post->post_type;

		$shell_meta_repaired = self::repair_shell_partial_meta_if_needed( $type_key, $filename, $post_id );
		if ( $shell_meta_repaired ) {
			clean_post_cache( $post_id );
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				return false;
			}
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Set shell partial key for %s.', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path
			);
		}

		$manifest_entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, $post );

		$content_changed = false;

		if ( ! ContentSync::markup_is_in_sync( $markup, (string) $post->post_content ) ) {
			$update = array(
				'ID' => $post_id,
				'post_content' => wp_slash( $markup ),
			);
			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$update['post_status'] = 'publish';
			}

			if ( null !== $manifest_entry ) {
				$manifest_title = ContentManifest::manifest_title_for_create( $manifest_entry, $filename );
				if ( '' !== $manifest_title ) {
					$update['post_title'] = $manifest_title;
				}

				$timestamp_fields = ContentManifest::manifest_timestamp_fields_for_update( $post, $manifest_entry, $existed_before );
				if ( array() !== $timestamp_fields ) {
					$update = array_merge( $update, $timestamp_fields );
				}
			}

			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				$messages[] = sprintf(
					/* translators: 1: relative path, 2: error */
					__( 'Failed to update %1$s: %2$s', \ASC_AI_PLUGIN_DOMAIN ),
					$relative_path,
					$result->get_error_message()
				);
				return false;
			}

			$content_changed = true;
			$imported_line = __( 'Imported %s.', \ASC_AI_PLUGIN_DOMAIN );
			$messages[] = sprintf( $imported_line, $relative_path );
		}

		if ( $content_changed ) {
			clean_post_cache( $post_id );
		}

		$title_slug_changed = false;
		if ( null !== $manifest_entry ) {
			$title_slug_changed = self::apply_manifest_title_slug_if_drifted(
				$post_id,
				$type_key,
				$filename,
				$manifest_entry,
				$relative_path,
				$messages
			);
		}

		if ( $title_slug_changed ) {
			clean_post_cache( $post_id );
		}

		$taxonomy_changed = false;
		if ( null !== $manifest_entry ) {
			$taxonomy_changed = ContentSync::apply_manifest_taxonomies_from_manifest_entry(
				$post_id,
				$post_type,
				$manifest_entry,
				$messages,
				$relative_path
			);
		}

		$timestamps_changed = false;
		if ( null !== $manifest_entry && ! $content_changed ) {
			clean_post_cache( $post_id );
			$post_for_timestamps = get_post( $post_id );
			if ( $post_for_timestamps instanceof WP_Post ) {
				$timestamps_changed = ContentManifest::apply_manifest_timestamps_from_entry(
					$post_id,
					$post_for_timestamps,
					$type_key,
					$manifest_entry,
					$relative_path,
					$existed_before,
					$messages
				);
			}
		}

		if ( $taxonomy_changed && ! $content_changed ) {
			$tax_line = __( 'Updated tags/categories from export for %s.', \ASC_AI_PLUGIN_DOMAIN );
			$messages[] = sprintf( $tax_line, $relative_path );
		}

		if ( $timestamps_changed && ! $content_changed ) {
			$time_line = __( 'Updated publication time from export for %s.', \ASC_AI_PLUGIN_DOMAIN );
			$messages[] = sprintf( $time_line, $relative_path );
		}

		if ( ! $existed_before && ! $content_changed && ! $title_slug_changed && ! $taxonomy_changed && ! $timestamps_changed ) {
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Created %s in WordPress from plugin files.', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path
			);
		}

		$file_normalized = ContentExporter::maybe_normalize_plugin_file_on_disk( $type_key, $filename, $markup, $messages );

		$companion_changed = false;
		if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
			$companion_changed = CompanionFileSync::import_companion_files_for_post( $post_id, $filename, $relative_path, $messages, $manifest_entry );
		}

		return $content_changed || $title_slug_changed || $taxonomy_changed || $timestamps_changed
			|| ! $existed_before
			|| $shell_meta_repaired
			|| $file_normalized
			|| $companion_changed;
	}

	/**
	 * Import one partial from content-manifest.json into {@see 'asc_boiler_partial'}.
	 *
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return bool True when a post was created or updated.
	 */
	public static function import_one_partial_from_manifest( string $filename, array &$messages ): bool {
		$type_key = SyncConfig::CONTENT_TYPE_PARTIALS;
		$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );
		$manifest_entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, null );
		if ( null === $manifest_entry ) {
			$partial_key = self::expected_partial_key_for_file( $filename, null );
			if ( '' === $partial_key ) {
				return false;
			}
			$manifest_entry = array( 'filename' => $filename );
		}

		$partial_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' === $partial_key ) {
			return false;
		}

		$markup = '';
		if ( is_file( ContentSync::resolve_content_file_path( $type_key, $filename ) ) ) {
			$markup = ContentSync::normalize_markup_for_storage( ContentSync::read_content_markup( $type_key, $filename ) );
		}

		$post = self::find_post_for_partial_filename( $filename, false );
		$existed_before = $post instanceof WP_Post;
		if ( null === $post ) {
			$post = self::create_partial_post_from_manifest( $filename, $manifest_entry, $partial_key );
		}
		if ( null === $post ) {
			return false;
		}

		$post_id = (int) $post->ID;
		$changed = false;

		if ( ! $existed_before ) {
			$changed = true;
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Created partial for %s.', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path
			);
		}

		if ( self::ensure_partial_shell_meta( $post_id, $filename, $manifest_entry ) ) {
			$changed = true;
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Set partial key for %s.', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $changed;
		}

		if ( '' !== trim( $markup ) && ! ContentSync::markup_is_in_sync( $markup, (string) $post->post_content ) ) {
			$update = array(
				'ID' => $post_id,
				'post_status' => 'publish',
				'post_content' => wp_slash( $markup ),
			);
			$manifest_title = ContentManifest::manifest_title_for_create( $manifest_entry, $filename );
			if ( '' !== $manifest_title ) {
				$update['post_title'] = $manifest_title;
			}
			$timestamp_fields = ContentManifest::manifest_timestamp_fields_for_update( $post, $manifest_entry, $existed_before );
			if ( array() !== $timestamp_fields ) {
				$update = array_merge( $update, $timestamp_fields );
			}

			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				$messages[] = sprintf(
					/* translators: 1: relative path, 2: error */
					__( 'Failed to update %1$s: %2$s', \ASC_AI_PLUGIN_DOMAIN ),
					$relative_path,
					$result->get_error_message()
				);
				return false;
			}

			$changed = true;
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Imported %s.', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path
			);
		}

		if ( null !== $manifest_entry ) {
			if ( self::apply_manifest_title_slug_if_drifted( $post_id, $type_key, $filename, $manifest_entry, $relative_path, $messages ) ) {
				$changed = true;
			}
		}

		if ( '' !== $markup && is_file( ContentSync::resolve_content_file_path( $type_key, $filename ) ) ) {
			if ( ContentExporter::maybe_normalize_plugin_file_on_disk( $type_key, $filename, $markup, $messages ) ) {
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Create a published {@see 'asc_boiler_partial'} post from a manifest partial row.
	 *
	 * @param string $filename HTML basename.
	 * @param array<string, mixed> $manifest_entry Manifest row.
	 * @param string $partial_key Logical partial key for post meta.
	 *
	 * @return WP_Post|null
	 */
	public static function create_partial_post_from_manifest( string $filename, array $manifest_entry, string $partial_key ): ?WP_Post {
		$title = ContentManifest::manifest_title_for_create( $manifest_entry, $filename );
		if ( '' === $title ) {
			$title = ContentSyncProfile::title_fallback_from_slug( str_replace( '_', '-', $partial_key ) );
		}
		if ( '' === $title ) {
			return null;
		}

		$slug = ContentSyncProfile::filename_to_slug( $filename );
		if ( isset( $manifest_entry['slug'] ) ) {
			$manifest_slug = sanitize_title( (string) $manifest_entry['slug'] );
			if ( '' !== $manifest_slug ) {
				$slug = $manifest_slug;
			}
		}
		if ( '' === $slug ) {
			$slug = sanitize_title( str_replace( '_', '-', $partial_key ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => 'asc_boiler_partial',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_name' => $slug,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		update_post_meta( $post_id, '_asc_ai_boiler_partial_key', $partial_key );

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}

	/**
	 * Create an empty published partial shell post with meta when none exists.
	 *
	 * @param string $partial_key Logical partial key.
	 * @param string $title Post title.
	 *
	 * @return WP_Post|null Post on success.
	 */
	public static function create_partial_shell_post_if_missing( string $partial_key, string $title ): ?WP_Post {
		$partial_key = trim( $partial_key );
		if ( '' === $partial_key ) {
			return null;
		}

		$existing = self::query_post_by_partial_key( $partial_key, true );
		if ( null !== $existing ) {
			return $existing;
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => 'asc_boiler_partial',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		update_post_meta( $post_id, '_asc_ai_boiler_partial_key', $partial_key );

		if ( $post instanceof WP_Post ) {
			return $post;
		}
		return null;
	}

	/**
	 * Query a partial CPT post by its logical partial key meta (`_asc_ai_boiler_partial_key`).
	 *
	 * @param string $partial_key Logical partial key.
	 * @param bool $any_editable_status Include non-published editable statuses.
	 *
	 * @return WP_Post|null
	 */
	public static function query_post_by_partial_key( string $partial_key, bool $any_editable_status ): ?WP_Post {
		$partial_key = trim( $partial_key );
		if ( '' === $partial_key ) {
			return null;
		}

		$statuses = array( 'publish' );
		if ( $any_editable_status ) {
			$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );
		}

		$query = new WP_Query(
			array(
				'post_type' => 'asc_boiler_partial',
				'post_status' => $statuses,
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'meta_key' => '_asc_ai_boiler_partial_key',
				'meta_value' => $partial_key,
				'meta_compare' => '=',
			)
		);

		if ( ! $query->have_posts() || ! $query->posts[0] instanceof WP_Post ) {
			wp_reset_postdata();
			return null;
		}

		$post = $query->posts[0];
		wp_reset_postdata();

		return $post;
	}

	/**
	 * Find a boiler partial post ({@see 'asc_boiler_partial'}) for a manifest HTML file.
	 *
	 * Ignores manifest `post_id`; matches logical partial key and slug only.
	 *
	 * @param string $filename Basename under content/partials/.
	 * @param bool $published_only When true, only published posts match.
	 *
	 * @return WP_Post|null
	 */
	public static function find_post_for_partial_filename( string $filename, bool $published_only ): ?WP_Post {
		$manifest_entry = ContentManifest::get_manifest_entry_for_file( SyncConfig::CONTENT_TYPE_PARTIALS, $filename, null );

		$partial_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' !== $partial_key ) {
			$post = self::query_post_by_partial_key( $partial_key, ! $published_only );
			if ( null !== $post ) {
				return $post;
			}
		}

		$slugs = array();
		if ( null !== $manifest_entry && isset( $manifest_entry['slug'] ) ) {
			$manifest_slug = sanitize_title( (string) $manifest_entry['slug'] );
			if ( '' !== $manifest_slug ) {
				$slugs[] = $manifest_slug;
			}
		}
		$file_slug = ContentSyncProfile::filename_to_slug( $filename );
		if ( '' !== $file_slug ) {
			$slugs[] = $file_slug;
		}

		$seen_slugs = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $seen_slugs[ $slug ] ) ) {
				continue;
			}
			$seen_slugs[ $slug ] = true;

			$post = ContentSync::query_post_by_slug( 'asc_boiler_partial', $slug, $published_only );
			if ( null !== $post ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Ensure {@see '_asc_ai_boiler_partial_key'} is set and the post uses the boiler partial CPT.
	 *
	 * @param int $post_id Partial post ID.
	 * @param string $filename Plugin partial HTML basename.
	 * @param array<string, mixed>|null $manifest_entry Optional manifest row.
	 *
	 * @return bool True when post type or meta was updated.
	 */
	public static function ensure_partial_shell_meta( int $post_id, string $filename, ?array $manifest_entry = null ): bool {
		$expected_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' === $expected_key ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'asc_boiler_partial' !== (string) $post->post_type ) {
			return false;
		}

		$changed = false;

		$current = trim( (string) get_post_meta( $post_id, '_asc_ai_boiler_partial_key', true ) );
		if ( $current !== $expected_key ) {
			update_post_meta( $post_id, '_asc_ai_boiler_partial_key', $expected_key );
			$changed = true;
		}

		return $changed;
	}

	public static function repair_shell_partial_meta_if_needed( string $type_key, string $filename, int $post_id ): bool {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
			return false;
		}

		$manifest_entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, null );
		$expected_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' === $expected_key ) {
			return false;
		}

		return self::ensure_partial_shell_meta( $post_id, $filename, $manifest_entry );
	}

	/**
	 * Ensure {@see '_asc_ai_boiler_partial_key'} matches the shell map for this file (fixes posts matched by slug only).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Basename.
	 * @param int $post_id Partial post ID.
	 *
	 * @return bool True when meta was created or updated.
	 */
	/**
	 * Resolve the logical partial key for a plugin HTML file (shell map, then manifest row).
	 *
	 * @param string $filename Basename under content/partials/.
	 * @param array<string, mixed>|null $manifest_entry Optional manifest row.
	 *
	 * @return string Empty when unknown.
	 */
	public static function expected_partial_key_for_file( string $filename, ?array $manifest_entry = null ): string {
		$shells = ContentSyncProfile::cpt_shell_map();
		if ( isset( $shells[ $filename ] ) ) {
			return trim( (string) $shells[ $filename ] );
		}

		if ( null !== $manifest_entry && isset( $manifest_entry['partial_key'] ) ) {
			$key = trim( trim( (string) $manifest_entry['partial_key'] ) );
			if ( '' !== $key ) {
				return $key;
			}
		}

		if ( null !== $manifest_entry && isset( $manifest_entry['slug'] ) ) {
			$key = trim( str_replace( '-', '_', trim( (string) $manifest_entry['slug'] ) ) );
			if ( '' !== $key ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Update title and slug from the manifest when they drifted from WordPress (same rules as pairing filename ↔ manifest slug).
	 *
	 * @param int $post_id Post ID.
	 * @param string $type_key Content type key.
	 * @param string $filename HTML basename.
	 * @param array<string, mixed> $manifest_entry Manifest row.
	 * @param string $relative_path Plugin-relative path for messages.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool True when the database was updated.
	 */
	public static function apply_manifest_title_slug_if_drifted(
		int $post_id,
		string $type_key,
		string $filename,
		array $manifest_entry,
		string $relative_path,
		array &$messages
	): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$update = array( 'ID' => $post_id );
		$manifest_title = ContentManifest::manifest_title_for_create( $manifest_entry, $filename );
		if ( '' !== $manifest_title && $manifest_title !== (string) $post->post_title ) {
			$update['post_title'] = $manifest_title;
		}

		$file_slug = ContentSyncProfile::filename_to_slug( $filename );
		if ( '' !== $file_slug ) {
			$manifest_slug = trim( (string) ( $manifest_entry['slug'] ?? '' ) );
			$slug_conflict = '' !== $manifest_slug && $manifest_slug !== $file_slug;
			if ( ! $slug_conflict && $file_slug !== (string) $post->post_name ) {
				$update['post_name'] = $file_slug;
			}
		}

		if ( count( $update ) === 1 ) {
			return false;
		}

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$update['post_status'] = 'publish';
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			$messages[] = sprintf(
				/* translators: 1: relative path, 2: error message */
				__( 'Failed to update title or slug for %1$s: %2$s', \ASC_AI_PLUGIN_DOMAIN ),
				$relative_path,
				(string) $result->get_error_message()
			);
			return false;
		}

		$line = __( 'Updated title or slug from export for %s.', \ASC_AI_PLUGIN_DOMAIN );
		$messages[] = sprintf( $line, $relative_path );

		return true;
	}

	/**
	 * Create a WordPress entry from a seed. Returns the new post (or null on failure).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename.
	 *
	 * @return WP_Post|null
	 */
	public static function create_post_from_seed( string $type_key, string $filename ): ?WP_Post {
		$title = self::seed_title_for( $type_key, $filename );
		if ( '' === $title ) {
			return null;
		}

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$partial_key = self::seed_partial_key_for( $type_key, $filename );
			if ( '' === $partial_key ) {
				return null;
			}
			return self::create_partial_shell_post_if_missing( $partial_key, $title );
		}

		if ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
			$resolve = self::seed_page_resolve_for( $type_key, $filename );
			if ( null === $resolve ) {
				return null;
			}
			return self::create_page_from_seed( $title, $resolve );
		}

		return null;
	}

	/**
	 * Create a published post from content-manifest.json when the HTML file exists but WordPress does not.
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename HTML basename.
	 *
	 * @return WP_Post|null
	 */
	public static function create_post_from_manifest( string $type_key, string $filename ): ?WP_Post {
		$types = ContentSyncProfile::sync_types();
		if ( ! isset( $types[ $type_key ] ) ) {
			return null;
		}
		$type_config = $types[ $type_key ];
		if ( ! ContentManifest::type_supports_manifest_driven_create( $type_key ) ) {
			return null;
		}

		$entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename );
		if ( null === $entry ) {
			return null;
		}

		if ( ContentSync::find_post_for_filename( $type_key, $filename, false ) instanceof WP_Post ) {
			return null;
		}

		$title = ContentManifest::manifest_title_for_create( $entry, $filename );
		if ( '' === $title ) {
			return null;
		}

		$slug = ContentSyncProfile::filename_to_slug( $filename );
		if ( isset( $entry['slug'] ) ) {
			$manifest_slug = sanitize_title( (string) $entry['slug'] );
			if ( '' !== $manifest_slug ) {
				$slug = $manifest_slug;
			}
		}
		if ( '' === $slug ) {
			return null;
		}

		$post_type = $type_config['post_type'];
		$post_type = $type_config['post_type'];

		$args = array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => '',
		);

		$post_id = wp_insert_post( $args, true );
		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			self::ensure_partial_shell_meta( $post_id, $filename, $entry );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}

	/**
	 * Insert a new published post from file basename when manifest-driven create did not run.
	 * Used as fallback if the manifest row is unusable (e.g. slug mismatch) or missing.
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename HTML basename.
	 *
	 * @return WP_Post|null
	 */
	public static function create_post_minimal_from_disk( string $type_key, string $filename ): ?WP_Post {
		$types = ContentSyncProfile::sync_types();
		if ( ! isset( $types[ $type_key ] ) ) {
			return null;
		}
		$type_config = $types[ $type_key ];
		if ( ! ContentManifest::type_supports_manifest_driven_create( $type_key ) ) {
			return null;
		}

		$slug = ContentSyncProfile::filename_to_slug( $filename );
		if ( '' === $slug ) {
			return null;
		}

		$title = ContentSyncProfile::title_fallback_from_slug( $slug );
		if ( '' === $title ) {
			return null;
		}

		if ( ContentSync::find_post_for_filename( $type_key, $filename, false ) instanceof WP_Post ) {
			return null;
		}

		$post_type = $type_config['post_type'];
		$post_type = $type_config['post_type'];

		$args = array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => '',
		);

		$post_id = wp_insert_post( $args, true );
		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$manifest_entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, null );
			self::ensure_partial_shell_meta( $post_id, $filename, $manifest_entry );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}

	/**
	 * Create a page from a seed (slug-based or front_page).
	 *
	 * @param string $title Page title.
	 * @param array{type:string, slug?:string, title:string} $resolve Seed resolver.
	 *
	 * @return WP_Post|null
	 */
	public static function create_page_from_seed( string $title, array $resolve ): ?WP_Post {
		$args = array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_content' => '',
		);

		if ( 'slug' === $resolve['type'] && isset( $resolve['slug'] ) && '' !== $resolve['slug'] ) {
			$args['post_name'] = (string) $resolve['slug'];
		}

		$post_id = wp_insert_post( $args, true );
		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( 'front_page' === $resolve['type'] ) {
			update_option( 'page_on_front', $post_id );
			update_option( 'show_on_front', 'page' );
		}

		return $post;
	}

	/**
	 * Title to assign when creating an entry from a seed (empty when no seed).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename.
	 *
	 * @return string
	 */
	public static function seed_title_for( string $type_key, string $filename ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, null );
			if ( null !== $entry && isset( $entry['title'] ) ) {
				$t = trim( (string) $entry['title'] );
				if ( '' !== $t ) {
					return $t;
				}
			}
			$partial_key = self::expected_partial_key_for_file( $filename, $entry );
			if ( '' !== $partial_key ) {
				return ContentSyncProfile::title_fallback_from_slug( str_replace( '_', '-', $partial_key ) );
			}
			return '';
		}
		if ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
			if ( ! isset( ContentSyncProfile::page_body_map()[ $filename ] ) ) {
				return '';
			}
			return (string) ContentSyncProfile::page_body_map()[ $filename ]['title'];
		}
		return '';
	}

	/**
	 * Partial key for a seeded partial filename (empty when not a partial seed).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename.
	 *
	 * @return string
	 */
	public static function seed_partial_key_for( string $type_key, string $filename ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
			return '';
		}

		$entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, null );
		return self::expected_partial_key_for_file( $filename, $entry );
	}

	/**
	 * Page resolve config for a seeded page filename (null when not a page seed).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename.
	 *
	 * @return array{type:string, slug?:string, title:string}|null
	 */
	public static function seed_page_resolve_for( string $type_key, string $filename ): ?array {
		if ( SyncConfig::CONTENT_TYPE_PAGES !== $type_key ) {
			return null;
		}
		if ( ! isset( ContentSyncProfile::page_body_map()[ $filename ] ) ) {
			return null;
		}
		return ContentSyncProfile::page_body_map()[ $filename ];
	}

	/**
	 * Resolve an existing page post from a seed resolver (no creation).
	 *
	 * @param array{type:string, slug?:string, title:string} $resolve Seed resolver.
	 *
	 * @return WP_Post|null
	 */
	public static function resolve_page_post( array $resolve ): ?WP_Post {
		if ( 'front_page' === $resolve['type'] ) {
			$page_id = (int) get_option( 'page_on_front' );
			if ( $page_id <= 0 ) {
				return null;
			}
			$post = get_post( $page_id );
			if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
				return null;
			}
			return $post;
		}

		if ( 'slug' !== $resolve['type'] ) {
			return null;
		}

		$slug = '';
		if ( isset( $resolve['slug'] ) ) {
			$slug = (string) $resolve['slug'];
		}
		if ( '' === $slug ) {
			return null;
		}

		$post = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		return $post;
	}

	/**
	 * Trash or delete published posts that no longer have a plugin HTML file (import cleanup).
	 *
	 * @param list<string> $messages Accumulated log lines.
	 *
	 * @return int Number of posts removed.
	 */
	public static function delete_orphan_wordpress_posts_for_import( array &$messages ): int {
		$orphans = ContentExporter::collect_orphan_wordpress_posts();
		if ( array() === $orphans ) {
			return 0;
		}

		$removed = 0;
		foreach ( $orphans as $row ) {
			$post_id = (int) $row['post_id'];
			$relative_path = (string) $row['relative_path'];
			$title = get_the_title( $post_id );

			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				$messages[] = sprintf(
					/* translators: 1: post title, 2: relative path */
					__( 'Skipped removing post "%1$s" (missing file %2$s): insufficient permission.', \ASC_AI_PLUGIN_DOMAIN ),
					$title,
					$relative_path
				);
				continue;
			}

			$result = wp_delete_post( $post_id, false );
			if ( false !== $result ) {
				++$removed;
				$removed_line = __( 'Removed WordPress post "%1$s" (missing export file %2$s, ID %3$d).', \ASC_AI_PLUGIN_DOMAIN );
				$messages[] = sprintf(
					$removed_line,
					$title,
					$relative_path,
					$post_id
				);
			} else {
				$messages[] = sprintf(
					/* translators: 1: post title, 2: relative path */
					__( 'Could not remove post "%1$s" (missing file %2$s).', \ASC_AI_PLUGIN_DOMAIN ),
					$title,
					$relative_path
				);
			}
		}

		return $removed;
	}

	/**
	 * When import has finished all file jobs, optionally remove published posts whose plugin file is gone.
	 *
	 * @param bool $confirmed Import was confirmed.
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return void
	 */
	public static function maybe_run_import_cleanup( bool $confirmed, array &$messages ): void {
		if ( ! $confirmed ) {
			return;
		}

		if ( ! SyncConfig::is_import_cleanup() ) {
			return;
		}

		self::delete_orphan_wordpress_posts_for_import( $messages );
	}

	/**
	 * Import content/media/ into the WordPress media library and apply manifest bindings.
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return void
	 */
	public static function maybe_import_plugin_media( array &$messages ): void {
		if ( ! SyncConfig::is_media_sync_enabled() ) {
			return;
		}
		$result = ContentMediaSync::import_from_plugin_files( $messages );
		if ( 0 === $result['processed'] ) {
			return;
		}

		if ( 0 === $result['updated'] ) {
			$messages[] = __(
				'Plugin media files already match the WordPress media library.',
				\ASC_AI_PLUGIN_DOMAIN
			);
		}
	}

}
