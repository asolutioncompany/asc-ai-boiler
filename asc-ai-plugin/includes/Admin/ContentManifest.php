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

final class ContentManifest {

	/**
	 * Relative filename of the content manifest inside content directory.
	 */
	public const CONTENT_MANIFEST_FILENAME = 'content-manifest.json';

	/**
	 * Cached `types` map from content-manifest.json.
	 *
	 * @var array<string, array<int, array<string, mixed>>>|null
	 */
	private static ?array $content_manifest_types = null;

	/**
	 * Absolute path to the content export manifest JSON (`content/content-manifest.json`).
	 */
	public static function get_content_manifest_path(): string {
	return ContentSync::get_content_directory() . self::CONTENT_MANIFEST_FILENAME;
	}

	/**
	 * Drop cached manifest types and version (call after manifest file changes on disk).
	 *
	 * @return void
	 */
	public static function invalidate_content_manifest_cache(): void {
		self::$content_manifest_types = null;
		ContentSyncProfile::invalidate_cache();
	}

	/**
	 * Load and cache the `types` section of content-manifest.json.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function load_content_manifest_types(): array {
		if ( null !== self::$content_manifest_types ) {
			return self::$content_manifest_types;
		}

		$path = self::get_content_manifest_path();
		if ( ! is_readable( $path ) ) {
			self::$content_manifest_types = array();
			return self::$content_manifest_types;
		}

		$json = file_get_contents( $path );
		if ( false === $json || '' === $json ) {
			self::$content_manifest_types = array();
			return self::$content_manifest_types;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['types'] ) || ! is_array( $data['types'] ) ) {
			self::$content_manifest_types = array();
			return self::$content_manifest_types;
		}

		/** @var array<string, array<int, array<string, mixed>>> $types */
		$types = $data['types'];
		foreach ( $types as $tk => $rows ) {
			if ( ! is_array( $rows ) ) {
				continue;
			}

			$normalized_rows = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$normalized_rows[] = self::normalize_manifest_entry( (string) $tk, $row );
			}
			$types[ $tk ] = self::dedupe_manifest_type_rows_last_filename_wins( $normalized_rows );
		}
		self::$content_manifest_types = $types;

		return self::$content_manifest_types;
	}

	/**
	 * Collapse duplicate manifest rows that share the same resolved filename (last occurrence wins).
	 *
	 * @param list<array<string, mixed>> $rows Rows under one `types` key.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function dedupe_manifest_type_rows_last_filename_wins( array $rows ): array {
		$by_file = array();
		foreach ( $rows as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$fn = self::manifest_entry_resolve_filename( $entry );
			if ( '' === $fn ) {
				continue;
			}
			$by_file[ $fn ] = $entry;
		}
		ksort( $by_file, SORT_STRING );
		return array_values( $by_file );
	}

	/**
	 * Normalize one manifest row after JSON decode.
	 *
	 * @param string $type_key Manifest `types` bucket key (e.g. partials, pages).
	 * @param array<string, mixed> $entry Raw row.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalize_manifest_entry( string $type_key, array $entry ): array {
		if ( isset( $entry['post_type'] ) ) {
			$entry['post_type'] = trim( (string) $entry['post_type'] );
		}

		if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
			return $entry;
		}

		$partial_key = '';
		if ( isset( $entry['partial_key'] ) ) {
			$partial_key = trim( (string) $entry['partial_key'] );
		}

		if ( '' === $partial_key ) {
			$filename = self::manifest_entry_resolve_filename( $entry );
			if ( '' !== $filename ) {
				$shells = ContentSyncProfile::cpt_shell_map();
				if ( isset( $shells[ $filename ] ) ) {
					$partial_key = (string) $shells[ $filename ];
				}
			}
		}

		if ( '' === $partial_key && isset( $entry['slug'] ) ) {
			$partial_key = str_replace( '-', '_', trim( (string) $entry['slug'] ) );
		}

		$partial_key = trim( $partial_key );
		if ( '' !== $partial_key ) {
			$entry['partial_key'] = $partial_key;
		}

		return $entry;
	}

	/**
	 * Cached `types` map from content-manifest.json (same cache as internal manifest helpers).
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function get_manifest_types_snapshot(): array {
		return self::load_content_manifest_types();
	}

	/**
	 * Resolve HTML basename from a manifest entry (`filename` and/or `slug`) for external profile builders.
	 *
	 * @param array<string, mixed> $entry Manifest row.
	 *
	 * @return string Basename or empty.
	 */
	public static function manifest_resolve_filename_from_entry( array $entry ): string {
		return self::manifest_entry_resolve_filename( $entry );
	}

	public static function manifest_entry_resolve_filename( array $entry ): string {
		if ( isset( $entry['filename'] ) ) {
			$raw = trim( (string) $entry['filename'] );
			if ( '' !== $raw ) {
				$fn = basename( $raw );
				if ( ContentSync::is_valid_content_filename( $fn ) ) {
					return $fn;
				}
			}
		}

		if ( isset( $entry['slug'] ) ) {
			$slug = trim( (string) $entry['slug'] );
			if ( '' !== $slug ) {
				$fn = $slug . '.html';
				if ( ContentSync::is_valid_content_filename( $fn ) ) {
					return $fn;
				}
			}
		}

		return '';
	}

	/**
	 * Manifest rows for a filename: the primary `types` bucket when it has any match; otherwise mis-keyed buckets.
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Basename.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function collect_manifest_entries_for_filename( string $type_key, string $filename ): array {
		$types = self::load_content_manifest_types();
		$type_config = ContentSyncProfile::sync_types()[ $type_key ] ?? null;
		$expected_pt = '';
		if ( is_array( $type_config ) && isset( $type_config['post_type'] ) ) {
			$expected_pt = (string) $type_config['post_type'];
		}
		$primary = array();

		if ( isset( $types[ $type_key ] ) && is_array( $types[ $type_key ] ) ) {
			foreach ( $types[ $type_key ] as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				if ( self::manifest_entry_resolve_filename( $entry ) === $filename ) {
					$primary[] = $entry;
				}
			}
		}

		if ( $primary !== array() ) {
			return $primary;
		}

		$out = array();
		foreach ( $types as $bucket_key => $rows ) {
			if ( $bucket_key === $type_key || ! is_array( $rows ) ) {
				continue;
			}
			foreach ( $rows as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				if ( self::manifest_entry_resolve_filename( $entry ) !== $filename ) {
					continue;
				}
				$mpt = (string) ( $entry['post_type'] ?? '' );
				if ( '' !== $expected_pt && '' !== $mpt && trim( $mpt ) !== trim( $expected_pt ) ) {
					continue;
				}
				$out[] = $entry;
			}
		}

		return $out;
	}

	/**
	 * Find a manifest row for a content type key and HTML filename.
	 *
	 * When $for_post is set, prefers the row whose metadata snapshot matches what export would write for that post
	 * (avoids stale duplicates). Otherwise returns the last matching row in file order.
	 *
	 * @param string $type_key Content type key (e.g. pages).
	 * @param string $filename Basename (e.g. about-us.html).
	 * @param WP_Post|null $for_post Canonical post for this file, when known.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_manifest_entry_for_file( string $type_key, string $filename, ?WP_Post $for_post = null ): ?array {
		$candidates = self::collect_manifest_entries_for_filename( $type_key, $filename );
		$n = count( $candidates );
		if ( 0 === $n ) {
			return null;
		}

		if ( $for_post instanceof WP_Post ) {
			$desired = self::build_export_manifest_row_from_post( $type_key, $for_post );
			if ( null !== $desired ) {
				$want = self::manifest_row_metadata_snapshot_for_compare( $desired );
				foreach ( $candidates as $entry ) {
					if ( self::manifest_row_metadata_snapshot_for_compare( $entry ) === $want ) {
						return $entry;
					}
				}
			}
		}

		return $candidates[ $n - 1 ];
	}

	/**
	 * Convert manifest publication datetime RFC 3339 (`date_gmt`) to MySQL GMT for wp_update_post.
	 *
	 * @param string $rfc3339 Date string from manifest.
	 *
	 * @return string MySQL `Y-m-d H:i:s` in UTC, or empty if invalid.
	 */
	public static function manifest_rfc3339_to_mysql_gmt( string $rfc3339 ): string {
		if ( '' === trim( $rfc3339 ) ) {
			return '';
		}

		$ts = strtotime( $rfc3339 );
		if ( false === $ts ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * True when the post’s GMT publication time already matches the manifest value (compares stored `post_date_gmt`).
	 *
	 * @param WP_Post $post Post.
	 * @param string $manifest_mysql_gmt MySQL `Y-m-d H:i:s` UTC from {@see self::manifest_rfc3339_to_mysql_gmt()}.
	 *
	 * @return bool
	 */
	public static function post_publication_gmt_matches_mysql( WP_Post $post, string $manifest_mysql_gmt ): bool {
		if ( '' === $manifest_mysql_gmt ) {
			return true;
		}

		$stored_raw = trim( (string) $post->post_date_gmt );
		if ( '' === $stored_raw || '0000-00-00 00:00:00' === $stored_raw ) {
			return false;
		}

		return $stored_raw === $manifest_mysql_gmt;
	}

	/**
	 * Fields to pass to wp_update_post so publication time matches the manifest (`post_id` ignored).
	 *
	 * When `date_gmt` is missing, empty, or not parseable, uses the current GMT time except for posts that were
	 * already published (`post_status` publish) before this import (`$existed_before` true), so export rows that
	 * omit the field do not wipe an existing publication date.
	 *
	 * @param WP_Post $post Post before update.
	 * @param array<string, mixed> $manifest_entry Manifest row.
	 * @param bool $existed_before True when the post existed in WordPress before this import job started.
	 *
	 * @return array<string, mixed> Non-empty when an update is needed (includes `edit_date` when applicable).
	 */
	public static function manifest_timestamp_fields_for_update( WP_Post $post, array $manifest_entry, bool $existed_before ): array {
		$out = array();

		$date_rfc = trim( (string) ( $manifest_entry['date_gmt'] ?? '' ) );
		$pub_mysql = self::manifest_rfc3339_to_mysql_gmt( $date_rfc );
		if ( '' === $pub_mysql ) {
			if ( $existed_before && 'publish' === (string) $post->post_status ) {
				return array();
			}
			$pub_mysql = gmdate( 'Y-m-d H:i:s' );
		}

		if ( self::post_publication_gmt_matches_mysql( $post, $pub_mysql ) ) {
			return array();
		}

		$out['post_date_gmt'] = $pub_mysql;
		$out['post_date'] = get_date_from_gmt( $pub_mysql );
		$out['edit_date'] = true;

		return $out;
	}

	/**
	 * Set publication time from the manifest when it differs (e.g. body and taxonomies already match).
	 *
	 * @param int $post_id Post ID.
	 * @param WP_Post $post Post (before update).
	 * @param string $type_key Content type key.
	 * @param array<string, mixed> $manifest_entry Manifest row.
	 * @param string $relative_path Plugin-relative path for messages.
	 * @param bool $existed_before Post existed before this import job started.
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return bool True when the database was updated.
	 */
	public static function apply_manifest_timestamps_from_entry(
		int $post_id,
		WP_Post $post,
		string $type_key,
		array $manifest_entry,
		string $relative_path,
		bool $existed_before,
		array &$messages
	): bool {
		$timestamp_fields = self::manifest_timestamp_fields_for_update( $post, $manifest_entry, $existed_before );
		if ( array() === $timestamp_fields ) {
			return false;
		}

		$update = array_merge(
			array( 'ID' => $post_id ),
			$timestamp_fields
		);

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$update['post_status'] = 'publish';
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			$err = __( 'Could not set publication time from export for %1$s: %2$s', \ASC_AI_PLUGIN_DOMAIN );
			$messages[] = sprintf(
				$err,
				$relative_path,
				$result->get_error_message()
			);
			return false;
		}

		return true;
	}

	/**
	 * Whether manifest- or disk-minimal-driven {@see wp_insert_post()} creation runs for this type.
	 * Pages are allowed (manifest row or slug-derived title). Partials use shell seeds + meta only.
	 *
	 * @param string $type_key Content type key.
	 *
	 * @return bool
	 */
	public static function type_supports_manifest_driven_create( string $type_key ): bool {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return false;
		}

		return true;
	}

	/**
	 * Title for manifest-driven create: explicit `title`, else a readable fallback from the file slug.
	 *
	 * @param array<string, mixed> $entry Manifest row.
	 * @param string $filename HTML basename.
	 *
	 * @return string Non-empty when a title can be inferred.
	 */
	public static function manifest_title_for_create( array $entry, string $filename ): string {
		if ( isset( $entry['title'] ) ) {
			$t = trim( (string) $entry['title'] );
			if ( '' !== $t ) {
				return $t;
			}
		}

		return ContentSyncProfile::title_fallback_from_slug( ContentSyncProfile::filename_to_slug( $filename ) );
	}

	/**
	 * One manifest types[] row as written during export (portable across installs: no post_id or modified_gmt).
	 *
	 * @param string $type_key Type key (pages, partials, etc.).
	 * @param WP_Post $post Published post.
	 *
	 * @return array<string, mixed>|null Null when no on-disk filename.
	 */
	public static function build_export_manifest_row_from_post( string $type_key, WP_Post $post ): ?array {
		$fresh = get_post( (int) $post->ID );
		if ( $fresh instanceof WP_Post ) {
			$post = $fresh;
		}

		$filename = ContentSync::derive_filename_for_post( $type_key, $post );
		if ( '' === $filename ) {
			return null;
		}

		$published = get_post_time( 'c', true, $post );

		$post_type = (string) $post->post_type;
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$post_type = 'asc_boiler_partial';
		}

		$date_gmt = '';
		if ( is_string( $published ) ) {
			$date_gmt = $published;
		}

		$row = array(
			'post_type' => $post_type,
			'title' => (string) $post->post_title,
			'slug' => (string) $post->post_name,
			'filename' => $filename,
			'date_gmt' => $date_gmt,
		);

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$partial_key = trim( (string) get_post_meta( (int) $post->ID, '_asc_ai_boiler_partial_key', true ) );
			if ( '' !== $partial_key ) {
				$row['partial_key'] = $partial_key;
			}
		} else {
			$txt_basename = CompanionFileSync::companion_text_basename( $filename );
			if ( '' !== $txt_basename ) {
				$row['excerpt'] = $txt_basename;
				if ( SyncConfig::is_yoast_sync() || ! CompanionFileSync::is_yoast_meta_description_active() ) {
					$row['meta_description'] = $txt_basename;
				}
			}
			if ( SyncConfig::is_yoast_sync() ) {
				$fb_title = trim( CompanionFileSync::get_post_meta_raw( (int) $post->ID, '_yoast_wpseo_opengraph-title' ) );
				if ( '' !== $fb_title ) {
					$row['social_title'] = $fb_title;
				}
				$tw_title = trim( CompanionFileSync::get_post_meta_raw( (int) $post->ID, '_yoast_wpseo_twitter-title' ) );
				if ( '' !== $tw_title ) {
					$row['x_title'] = $tw_title;
				}
				$focus_kw = trim( CompanionFileSync::get_post_meta_raw( (int) $post->ID, '_yoast_wpseo_focuskw' ) );
				if ( '' !== $focus_kw ) {
					$row['focus_keyphrase'] = $focus_kw;
				}
			}
		}

		return array_merge( $row, ContentSync::manifest_taxonomy_lists_for_post( $post ) );
	}

	/**
	 * Sort manifest-style category/tag rows by term slug for stable comparison.
	 *
	 * @param list<array<string, mixed>> $categories Term rows.
	 * @param list<array<string, mixed>> $tags Term rows.
	 *
	 * @return array{categories: list<array{slug: string, name: string}>, tags: list<array{slug: string, name: string}>}
	 */
	public static function manifest_taxonomy_columns_sorted( array $categories, array $tags ): array {
		$out = array();
		$buckets = array(
			'categories' => $categories,
			'tags' => $tags,
		);
		foreach ( $buckets as $tax_key => $rows ) {
			$list = array();
			foreach ( $rows as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$list[] = array(
					'slug' => self::normalize_manifest_compare_scalar( (string) ( $item['slug'] ?? '' ) ),
					'name' => self::normalize_manifest_compare_scalar( (string) ( $item['name'] ?? '' ) ),
				);
			}

			usort(
				$list,
				static function ( array $a, array $b ): int {
					return strcmp( $a['slug'], $b['slug'] );
				}
			);
			$out[ $tax_key ] = $list;
		}

		return $out;
	}

	/**
	 * Scalar trim for manifest metadata compare (Unicode whitespace; ASCII trim misses NBSP, etc.).
	 *
	 * @param string $value Raw field.
	 *
	 * @return string
	 */
	public static function normalize_manifest_compare_scalar( string $value ): string {
		$out = preg_replace( '/^\s+|\s+$/u', '', $value );
		if ( ! is_string( $out ) ) {
			return '';
		}
		return $out;
	}

	/**
	 * Strict comparable snapshot: post type, title, slug, filename, sorted taxonomy rows (slug + name).
	 *
	 * Omits publication timestamps (`date_gmt`). Legacy keys like `post_id` or `modified_gmt` are ignored.
	 *
	 * @param array<string, mixed> $row Manifest row.
	 *
	 * @return array{
	 *   post_type: string,
	 *   title: string,
	 *   slug: string,
	 *   filename: string,
	 *   categories: list<array{slug: string, name: string}>,
	 *   tags: list<array{slug: string, name: string}>
	 * }
	 */
	public static function manifest_row_metadata_snapshot_for_compare( array $row ): array {
		$categories = array();
		if ( isset( $row['categories'] ) && is_array( $row['categories'] ) ) {
			$categories = $row['categories'];
		}
		$tags = array();
		if ( isset( $row['tags'] ) && is_array( $row['tags'] ) ) {
			$tags = $row['tags'];
		}
		$tax = self::manifest_taxonomy_columns_sorted( $categories, $tags );

		$post_type = (string) ( $row['post_type'] ?? '' );
		$post_type = trim( $post_type );

		$snapshot = array(
			'post_type' => self::normalize_manifest_compare_scalar( $post_type ),
			'title' => self::normalize_manifest_compare_scalar( (string) ( $row['title'] ?? '' ) ),
			'slug' => self::normalize_manifest_compare_scalar( (string) ( $row['slug'] ?? '' ) ),
			'filename' => self::normalize_manifest_compare_scalar( (string) ( $row['filename'] ?? '' ) ),
			'excerpt' => self::normalize_manifest_compare_scalar( (string) ( $row['excerpt'] ?? '' ) ),
			'categories' => $tax['categories'],
			'tags' => $tax['tags'],
		);

		if ( SyncConfig::is_yoast_sync() || ! CompanionFileSync::is_yoast_meta_description_active() ) {
			$snapshot['meta_description'] = self::normalize_manifest_compare_scalar( (string) ( $row['meta_description'] ?? '' ) );
		}

		if ( SyncConfig::is_yoast_sync() ) {
			$snapshot['social_title'] = self::normalize_manifest_compare_scalar( (string) ( $row['social_title'] ?? '' ) );
			$snapshot['x_title'] = self::normalize_manifest_compare_scalar( (string) ( $row['x_title'] ?? '' ) );
			$snapshot['focus_keyphrase'] = self::normalize_manifest_compare_scalar( (string) ( $row['focus_keyphrase'] ?? '' ) );
		}

		return $snapshot;
	}

	/**
	 * True when the on-disk manifest row for this file does not match post fields that export would
	 * rewrite aside from publication/modified timestamps on disk (ignored for this comparison; see {@see ContentSync::describe_publication_drift_for_detect()}).
	 *
	 * @param string $type_key Type key.
	 * @param string $filename Basename.
	 * @param WP_Post $post Post.
	 *
	 * @return bool
	 */
	public static function export_manifest_row_differs_from_post( string $type_key, string $filename, WP_Post $post ): bool {
		$desired = self::build_export_manifest_row_from_post( $type_key, $post );
		if ( null === $desired ) {
			return false;
		}

		$entry = self::get_manifest_entry_for_file( $type_key, $filename, $post );
		$manifest_path = self::get_content_manifest_path();
		$manifest_readable = is_readable( $manifest_path );

		if ( null === $entry ) {
			return $manifest_readable;
		}

		return self::manifest_row_metadata_snapshot_for_compare( $desired )
			!== self::manifest_row_metadata_snapshot_for_compare( $entry );
	}

	/**
	 * Rows omit `post_id` and `modified_gmt` so manifests are portable across WordPress installs.
	 *
	 * Called when an export run completes ({@see ContentExporter::run_export_batch()}) and when an import run finishes
	 * ({@see self::maybe_normalize_content_manifest_from_wordpress()}).
	 *
	 * @return bool True when the file was written successfully.
	 */
	public static function write_content_export_manifest( array &$messages ): bool {
		ContentSync::ensure_content_directories_exist();

		$existing_manifest = array();
		$path = self::get_content_manifest_path();
		if ( is_readable( $path ) ) {
			$json_content = file_get_contents( $path );
			if ( false !== $json_content && '' !== $json_content ) {
				$decoded = json_decode( $json_content, true );
				if ( is_array( $decoded ) ) {
					$existing_manifest = $decoded;
				}
			}
		}

		$types_out = array();
		foreach ( ContentSyncProfile::all_sync_types() as $type_key => $type_config ) {
			if ( ! SyncConfig::is_content_type_enabled( $type_key ) ) {
				if ( isset( $existing_manifest['types'][ $type_key ] ) && is_array( $existing_manifest['types'][ $type_key ] ) ) {
					$types_out[ $type_key ] = $existing_manifest['types'][ $type_key ];
				}
				continue;
			}

			$entries_by_filename = array();
			foreach ( ContentSync::query_posts_for_type( $type_config['post_type'] ) as $post ) {
				$row = self::build_export_manifest_row_from_post( $type_key, $post );
				if ( null === $row ) {
					continue;
				}
				$fn = (string) ( $row['filename'] ?? '' );
				if ( '' === $fn ) {
					continue;
				}
				$canonical = ContentSync::find_post_for_filename( $type_key, $fn, true );
				if ( null === $canonical || (int) $canonical->ID !== (int) $post->ID ) {
					continue;
				}
				$entries_by_filename[ $fn ] = $row;
			}
			ksort( $entries_by_filename, SORT_STRING );
			$types_out[ $type_key ] = array_values( $entries_by_filename );
		}

		if ( SyncConfig::is_media_sync_enabled() ) {
			$media_out = ContentMediaSync::build_manifest_media_rows_for_manifest();
			$bindings_out = ContentMediaSync::build_manifest_media_bindings_for_export();
		} else {
			$media_out = array();
			if ( isset( $existing_manifest['media'] ) && is_array( $existing_manifest['media'] ) ) {
				$media_out = $existing_manifest['media'];
			}
			$bindings_out = array();
			if ( isset( $existing_manifest['media_bindings'] ) && is_array( $existing_manifest['media_bindings'] ) ) {
				$bindings_out = $existing_manifest['media_bindings'];
			}
		}

		$payload = array(
			'manifest_version' => 1,
			'exported_at' => gmdate( 'c' ),
			'types' => $types_out,
			'media' => $media_out,
			'media_bindings' => $bindings_out,
		);

		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}

		$json = wp_json_encode( $payload, $flags );
		if ( false === $json ) {
			return false;
		}

		$written = file_put_contents( self::get_content_manifest_path(), $json, LOCK_EX );
		if ( false !== $written ) {
			self::invalidate_content_manifest_cache();
			$messages[] = __( 'Updated content-manifest.json.', \ASC_AI_PLUGIN_DOMAIN );
		}
		return false !== $written;
	}

	/**
	 * Regenerate content-manifest.json from WordPress after import (canonical export form).
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool True when the manifest file was written.
	 */
	public static function maybe_normalize_content_manifest_from_wordpress( array &$messages ): bool {
		if ( ! self::write_content_export_manifest( $messages ) ) {
			$messages[] = __(
				'Failed to normalize content-manifest.json. Check file permissions.',
				\ASC_AI_PLUGIN_DOMAIN
			);

			return false;
		}

		$messages[] = __(
			'Normalized content-manifest.json to canonical export form from WordPress.',
			\ASC_AI_PLUGIN_DOMAIN
		);

		return true;
	}

}
