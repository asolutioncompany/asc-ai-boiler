<?php
/**
 * Static content sync (plugin HTML and content-manifest.json vs WordPress).
 *
 * Pairs every WordPress entity of each supported type (pages, posts, partials, and custom post types) with a file under `content/<type>/`. Restore applies on-disk HTML to
 * WordPress when normalized file body differs from normalized database body (UTF-8 BOM ignored,
 * CRLF vs LF ignored, leading/trailing document whitespace ignored). Tags and categories from content-manifest.json replace
 * WordPress terms for each listed taxonomy when a manifest row matches the file. Publication time (`date_gmt`)
 * from the manifest is applied when it differs, even if the file body already matches WordPress. Rows may omit
 * `date_gmt`; restore then stamps the current GMT time (unless the post was already published before that restore).
 * When restore finishes, content-manifest.json is regenerated from WordPress (same canonical form as backup) so
 * manifest metadata matches the database. Manifest rows omit WordPress `post_id` and last-modified times so the
 * same files port across environments; restore does not set modified times from the manifest. Restore also rewrites
 * each scanned plugin HTML file on disk to canonical backup form when raw bytes differ (BOM, CRLF vs LF, outer trim).
 * Restore imports content/media/ into the WordPress media library and applies manifest media bindings. Optional restore cleanup
 * can remove published posts whose expected plugin file was removed from disk. Backup writes every
 * published entity to plugin files, exports bound media to content/media/, regenerates content-manifest.json from the database (including
 * tags, categories, and publication date), and optionally deletes plugin HTML that has no matching published content. The sync
 * admin screen runs file sync through AJAX in batches of {@see SyncConfig::CONTENT_SYNC_BATCH_SIZE}.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

use ASC\AI_BOILER\Core\ContentMediaSync;
use ASC\AI_BOILER\Core\PartialStore;
use ASC\AI_BOILER\Core\RegisterPartials;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Static HTML sync between plugin content files and WordPress posts.
 */
final class ContentSync {

/**
	 * Filter: extend the list of `content/<key>/` subdirectory keys participating in backup/restore.
	 *
	 * Callback receives `list<string>` (partials, pages, posts) and must return `list<string>` (append-only is typical).
	 */
	public const FILTER_SYNC_CONTENT_TYPE_KEYS = 'asc_ai_boiler_sync_content_type_keys';

	/**
	 * Filter: override the absolute path to the `content/` directory (trailing slash).
	 *
	 * Callback receives the default path and must return a string.
	 */
	public const FILTER_CONTENT_DIR = 'asc_ai_boiler_content_dir';

	/**
	 * Filter: override the public URL of the `content/` directory (trailing slash).
	 *
	 * Callback receives the default URL and must return a string.
	 */
	public const FILTER_CONTENT_URL = 'asc_ai_boiler_content_url';

	/**
	 * JSON manifest at the content root (titles, slugs, filenames, dates).
	 *
	 * @var string
	 */
	public const CONTENT_MANIFEST_FILENAME = 'content-manifest.json';

	/**
	 * @return list<string>
	 */
	public static function get_content_type_keys(): array {
		$builtin = array(
			SyncConfig::CONTENT_TYPE_PARTIALS,
			SyncConfig::CONTENT_TYPE_PAGES,
			SyncConfig::CONTENT_TYPE_POSTS,
		);

		/** @var list<string> $filtered */
		$filtered = apply_filters( self::FILTER_SYNC_CONTENT_TYPE_KEYS, $builtin );

		if ( ! is_array( $filtered ) ) {
			return $builtin;
		}

		$out = array();
		foreach ( $filtered as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$key = trim( $key );
			if ( '' === $key ) {
				continue;
			}
			$out[] = $key;
		}

		return $out;
	}

	/**
	 * Absolute path to the `content/` directory (trailing slash). Filtered by {@see FILTER_CONTENT_DIR}.
	 */
	public static function get_content_directory(): string {
		$default = plugin_dir_path( \ASC_AI_PLUGIN_FILE ) . SyncConfig::CONTENT_RELATIVE_ROOT;
		return trailingslashit( (string) apply_filters( self::FILTER_CONTENT_DIR, $default ) );
	}

	/**
	 * Public URL of the `content/` directory (trailing slash). Filtered by {@see FILTER_CONTENT_URL}.
	 */
	public static function get_content_url(): string {
		$default = plugin_dir_url( \ASC_AI_PLUGIN_FILE ) . SyncConfig::CONTENT_RELATIVE_ROOT;
		return trailingslashit( (string) apply_filters( self::FILTER_CONTENT_URL, $default ) );
	}

	/**
	 * Relative plugin path to a file under content/{type}/ (for log lines and UI).
	 *
	 * @param string $type_key Content type key (e.g. pages).
	 * @param string $filename Basename (e.g. home.html).
	 *
	 * @return string e.g. content/pages/home.html
	 */
	public static function relative_content_type_file_path( string $type_key, string $filename ): string {
		return SyncConfig::CONTENT_RELATIVE_ROOT . $type_key . '/' . $filename;
	}

	/**
	 * Absolute path to the content export manifest JSON (`content/content-manifest.json`).
	 */
	public static function get_content_manifest_path(): string {
		return self::get_content_directory() . self::CONTENT_MANIFEST_FILENAME;
	}

	/**
	 * Absolute path of a content type subdirectory (trailing slash).
	 *
	 * @param string $type Content type key.
	 *
	 * @return string Empty string for unknown type.
	 */
	public static function get_content_type_directory( string $type ): string {
		if ( ! self::is_valid_content_type( $type ) ) {
			return '';
		}

		return self::get_content_directory() . $type . '/';
	}

	/**
	 * Whether a string is a known content type subdirectory key.
	 */
	public static function is_valid_content_type( string $type ): bool {
		return in_array( $type, self::get_content_type_keys(), true );
	}

	/**
	 * Validate a content filename: must be a basename and end with `.html`.
	 */
	public static function is_valid_content_filename( string $filename ): bool {
		if ( '' === $filename ) {
			return false;
		}
		if ( basename( $filename ) !== $filename ) {
			return false;
		}
		if ( '.html' !== substr( $filename, -5 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Remove a leading UTF-8 byte order mark if present.
	 */
	public static function strip_utf8_bom( string $markup ): string {
		if ( str_starts_with( $markup, "\xEF\xBB\xBF" ) ) {
			return substr( $markup, 3 );
		}

		return $markup;
	}

	/**
	 * Normalize markup read from disk: no BOM, Unix line endings (matches restore/compare expectations).
	 */
	public static function normalize_content_markup_from_disk( string $markup ): string {
		$markup = self::strip_utf8_bom( $markup );

		return str_replace( array( "\r\n", "\r" ), "\n", $markup );
	}

	/**
	 * Canonical HTML for plugin backup files: no BOM, Unix newlines, trim outer whitespace.
	 *
	 * Pass post content unslashed or raw file body; {@see write_content_markup()} applies this before writing.
	 */
	public static function normalize_markup_for_storage( string $markup ): string {
		$markup = self::strip_utf8_bom( $markup );
		$normalized = str_replace( array( "\r\n", "\r" ), "\n", $markup );

		return trim( $normalized );
	}

	/**
	 * Ensure the content directory and per-type subdirectories exist.
	 */
	public static function ensure_content_directories_exist(): void {
		$root = self::get_content_directory();
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}

		foreach ( self::get_content_type_keys() as $type ) {
			$type_dir = self::get_content_type_directory( $type );
			if ( ! is_dir( $type_dir ) ) {
				wp_mkdir_p( $type_dir );
			}
		}

		ContentMediaSync::ensure_media_directory_exists();
	}

	/**
	 * Read content markup for a (type, filename) pair.
	 *
	 * @param string $type Content type key.
	 * @param string $filename Basename (e.g. `header.html`).
	 *
	 * @return string Empty string if missing or invalid.
	 */
	public static function read_content_markup( string $type, string $filename ): string {
		if ( ! self::is_valid_content_type( $type ) ) {
			return '';
		}
		if ( ! self::is_valid_content_filename( $filename ) ) {
			return '';
		}

		$path = self::resolve_content_file_path( $type, $filename );
		if ( ! is_file( $path ) ) {
			return '';
		}

		$markup = file_get_contents( $path );
		if ( false === $markup ) {
			return '';
		}

		return self::normalize_content_markup_from_disk( $markup );
	}

	/**
	 * Write content markup for a (type, filename) pair (creates the type subdir if missing).
	 *
	 * @return bool True on success.
	 */
	public static function write_content_markup( string $type, string $filename, string $markup ): bool {
		if ( ! self::is_valid_content_type( $type ) ) {
			return false;
		}
		if ( ! self::is_valid_content_filename( $filename ) ) {
			return false;
		}

		$type_dir = wp_normalize_path( self::get_content_type_directory( $type ) );
		$target = wp_normalize_path( $type_dir . $filename );
		if ( 0 !== strpos( $target, $type_dir ) ) {
			return false;
		}

		if ( ! is_dir( $type_dir ) ) {
			wp_mkdir_p( $type_dir );
		}

		$to_write = self::normalize_markup_for_storage( wp_unslash( $markup ) );

		return self::write_file_atomically( $target, $to_write );
	}

	/**
	 * Delete a content file if it exists (basename only, under the type subdir).
	 *
	 * @return bool True when the file was present and removed.
	 */
	public static function delete_content_file( string $type, string $filename ): bool {
		if ( ! self::is_valid_content_type( $type ) ) {
			return false;
		}
		if ( ! self::is_valid_content_filename( $filename ) ) {
			return false;
		}

		$type_dir = wp_normalize_path( self::get_content_type_directory( $type ) );
		$target = wp_normalize_path( $type_dir . $filename );
		if ( 0 !== strpos( $target, $type_dir ) ) {
			return false;
		}

		if ( ! file_exists( $target ) || ! is_file( $target ) ) {
			return false;
		}

		return unlink( $target );
	}

	/**
	 * List `.html` files in a content type subdirectory (basenames, sorted).
	 *
	 * @return list<string>
	 */
	public static function list_content_files( string $type ): array {
		if ( ! self::is_valid_content_type( $type ) ) {
			return array();
		}

		$type_dir = self::get_content_type_directory( $type );
		if ( ! is_dir( $type_dir ) ) {
			return array();
		}

		$entries = scandir( $type_dir );
		if ( false === $entries ) {
			return array();
		}

		$files = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( ! self::is_valid_content_filename( $entry ) ) {
				continue;
			}
			if ( ! is_file( $type_dir . $entry ) ) {
				continue;
			}
			$files[] = $entry;
		}

		sort( $files );
		return $files;
	}

	/**
	 * Absolute on-disk HTML path under `content/{type}/`.
	 *
	 * @param string $type Content type key.
	 * @param string $filename HTML basename.
	 *
	 * @return string Absolute path (may not exist).
	 */
	private static function resolve_content_file_path( string $type, string $filename ): string {
		return self::get_content_type_directory( $type ) . $filename;
	}

	/**
	 * @param string $directory Absolute directory with trailing slash.
	 *
	 * @return list<string> HTML basenames.
	 */
	private static function list_html_files_in_directory( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$entries = scandir( $directory );
		if ( false === $entries ) {
			return array();
		}

		$files = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( ! self::is_valid_content_filename( $entry ) ) {
				continue;
			}
			if ( ! is_file( $directory . $entry ) ) {
				continue;
			}
			$files[] = $entry;
		}

		sort( $files );
		return $files;
	}

	/**
	 * Partial restore filenames: every row in content-manifest.json `types.partials` (authoritative).
	 *
	 * @return list<string>
	 */
	private static function collect_partial_filenames_from_manifest(): array {
		$names = array();
		$manifest_types = self::load_content_manifest_types();
		if ( isset( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] )
			&& is_array( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] ) ) {
			foreach ( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$fn = self::manifest_entry_resolve_filename( $entry );
				if ( self::is_valid_content_filename( $fn ) ) {
					$names[] = $fn;
				}
			}
		}

		foreach ( self::list_content_files( SyncConfig::CONTENT_TYPE_PARTIALS ) as $filename ) {
			$names[] = $filename;
		}

		$names = array_values( array_unique( $names ) );
		sort( $names, SORT_STRING );

		return $names;
	}

	/**
	 * Create published partial shells for manifest rows that are not in WordPress yet.
	 *
	 * @return void
	 */
	private static function ensure_partial_posts_from_manifest(): void {
		$manifest_types = self::load_content_manifest_types();
		if ( ! isset( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] )
			|| ! is_array( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] ) ) {
			return;
		}

		foreach ( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$filename = self::manifest_entry_resolve_filename( $entry );
			if ( '' === $filename || ! self::is_valid_content_filename( $filename ) ) {
				continue;
			}

			if ( self::find_post_for_partial_filename( $filename, false ) instanceof WP_Post ) {
				continue;
			}

			$partial_key = self::expected_partial_key_for_file( $filename, $entry );
			if ( '' === $partial_key ) {
				continue;
			}

			$title = self::manifest_title_for_create( $entry, $filename );
			if ( '' === $title ) {
				$title = self::title_fallback_from_slug( str_replace( '_', '-', $partial_key ) );
			}
			if ( '' === $title ) {
				continue;
			}

			PartialStore::create_shell_post_if_missing( $partial_key, $title );
		}

		PartialStore::invalidate_cache();
	}

	/**
	 * Atomically write file contents with an exclusive lock.
	 *
	 * @return bool
	 */
	private static function write_file_atomically( string $target, string $contents ): bool {
		$directory = dirname( $target );
		if ( ! is_dir( $directory ) || ! is_writable( $directory ) ) {
			return false;
		}

		$temp_path = tempnam( $directory, 'asc-boiler-' );
		if ( false === $temp_path ) {
			return false;
		}

		$written = file_put_contents( $temp_path, $contents, LOCK_EX );
		if ( false === $written ) {
			unlink( $temp_path );
			return false;
		}

		$temp_permissions_set = chmod( $temp_path, 0664 );
		if ( ! $temp_permissions_set ) {
			unlink( $temp_path );
			return false;
		}

		$renamed = rename( $temp_path, $target );
		if ( ! $renamed ) {
			unlink( $temp_path );
			return false;
		}

		$target_permissions_set = chmod( $target, 0664 );
		if ( ! $target_permissions_set ) {
			return false;
		}

		return true;
	}

	public const AJAX_ACTION_RESTORE_BATCH = 'asc_ai_boiler_restore_batch';

	public const AJAX_ACTION_BACKUP_BATCH = 'asc_ai_boiler_backup_batch';

	public const AJAX_ACTION_DETECT_DIFFERENCES = 'asc_ai_boiler_detect_differences';

	private const NONCE_ACTION = 'asc_ai_boiler_sync';

	/**
	 * Cached manifest `types` map from content-manifest.json (null = not loaded yet).
	 *
	 * @var array<string, array<int, array<string, mixed>>>|null
	 */
	private static $content_manifest_types = null;

	/**
	 * Public list of content types in canonical iteration order: { key, label }.
	 *
	 * @return list<array{key:string, label:string}>
	 */
	public static function get_type_list(): array {
		return ContentSyncProfile::type_list();
	}

	/**
	 * Find the WP post that the given (type, filename) pair refers to.
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename (basename).
	 *
	 * @return WP_Post|null
	 */
	private static function find_post_for_filename( string $type_key, string $filename, bool $published_only = true ): ?WP_Post {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return self::find_post_for_partial_filename( $filename, $published_only );
		}

		if ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
			if ( isset( ContentSyncProfile::page_body_map()[ $filename ] ) ) {
				$resolved = self::resolve_page_post( ContentSyncProfile::page_body_map()[ $filename ] );
				if ( $resolved instanceof WP_Post ) {
					if ( $published_only && 'publish' !== $resolved->post_status ) {
						return null;
					}
					return $resolved;
				}
				return null;
			}
		}

		$slug = self::filename_to_slug( $filename );
		if ( '' === $slug ) {
			return null;
		}

		$sync_types = ContentSyncProfile::sync_types();
		if ( ! isset( $sync_types[ $type_key ] ) ) {
			return null;
		}

		$post_type = $sync_types[ $type_key ]['post_type'];

		if ( 'page' === $post_type ) {
			$statuses = $published_only ? array( 'publish' ) : array( 'publish', 'draft', 'pending', 'future', 'private' );
			$query = new WP_Query(
				array(
					'post_type' => 'page',
					'post_status' => $statuses,
					'name' => $slug,
					'posts_per_page' => 1,
					'no_found_rows' => true,
					'ignore_sticky_posts' => true,
				)
			);
			$post = null;
			if ( $query->have_posts() && $query->posts[0] instanceof WP_Post ) {
				$post = $query->posts[0];
			}
			wp_reset_postdata();

			return $post;
		}

		$statuses = $published_only ? array( 'publish' ) : array( 'publish', 'draft', 'pending', 'future', 'private' );
		$query = new WP_Query(
			array(
				'post_type' => $post_type,
				'post_status' => $statuses,
				'name' => $slug,
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
			)
		);

		$post = null;
		if ( $query->have_posts() && $query->posts[0] instanceof WP_Post ) {
			$post = $query->posts[0];
		}
		wp_reset_postdata();

		return $post;
	}

	/**
	 * Filename basename without `.html` extension.
	 *
	 * @param string $filename Filename.
	 *
	 * @return string
	 */
	private static function filename_to_slug( string $filename ): string {
		if ( '.html' !== substr( $filename, -5 ) ) {
			return '';
		}
		return substr( $filename, 0, -5 );
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

	/**
	 * Cached `types` map from content-manifest.json (same cache as internal manifest helpers).
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function get_manifest_types_snapshot(): array {
		return self::load_content_manifest_types();
	}

	private static function manifest_entry_resolve_filename( array $entry ): string {
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
	 * Title for manifest-driven create: explicit `title`, else a readable fallback from the file slug.
	 *
	 * @param array<string, mixed> $entry Manifest row.
	 * @param string $filename HTML basename.
	 *
	 * @return string Non-empty when a title can be inferred.
	 */
	private static function manifest_title_for_create( array $entry, string $filename ): string {
		if ( isset( $entry['title'] ) ) {
			$t = trim( (string) $entry['title'] );
			if ( '' !== $t ) {
				return $t;
			}
		}

		return self::title_fallback_from_slug( self::filename_to_slug( $filename ) );
	}

	/**
	 * @param string $slug URL slug without `.html`.
	 *
	 * @return string
	 */
	private static function title_fallback_from_slug( string $slug ): string {
		if ( '' === $slug ) {
			return '';
		}

		$s = trim( str_replace( array( '-', '_' ), ' ', $slug ) );
		if ( '' === $s ) {
			return '';
		}

		return ucwords( $s );
	}

	/**
	 * Flat list of import jobs in canonical type + filename order.
	 *
	 * @return list<array{type:string, filename:string}>
	 */
	private static function collect_restore_file_jobs(): array {
		$jobs = array();
		foreach ( ContentSyncProfile::sync_types() as $type_key => $_unused ) {
			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$filenames = self::collect_partial_filenames_from_manifest();
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
	 *   messages:list<string>
	 * }
	 */
	public static function run_restore_batch( int $offset, bool $confirmed ): array {
		if ( ! $confirmed ) {
			return array(
				'ok' => false,
				'done' => true,
				'next_offset' => 0,
				'updated_in_batch' => 0,
				'processed_in_batch' => 0,
				'total_jobs' => 0,
				'messages' => array( __( 'Restore was not confirmed.', \ASC_AI_BOILER_TEXT_DOMAIN ) ),
			);
		}

		$jobs = self::collect_restore_file_jobs();
		$total_jobs = count( $jobs );
		$offset = max( 0, $offset );

		if ( 0 === $offset ) {
			self::invalidate_content_manifest_cache();
			self::ensure_partial_posts_from_manifest();
		}

		if ( $offset >= $total_jobs ) {
			$messages = array();
			if ( $offset > 0 && $confirmed ) {
				self::maybe_run_restore_cleanup( $confirmed, $messages );
				self::maybe_restore_plugin_media( $messages );
				self::maybe_normalize_content_manifest_from_wordpress( $messages );
			}

			return array(
				'ok' => true,
				'done' => true,
				'next_offset' => $total_jobs,
				'updated_in_batch' => 0,
				'processed_in_batch' => 0,
				'total_jobs' => $total_jobs,
				'messages' => $messages,
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
			if ( self::restore_one_file( $type_key, $filename, $messages ) ) {
				$updated_in_batch++;
			}
			$processed_in_batch++;
		}

		$next_offset = $offset + $processed_in_batch;

		$done = $next_offset >= $total_jobs;
		if ( $done && $confirmed && $total_jobs > 0 ) {
			self::maybe_run_restore_cleanup( $confirmed, $messages );
			self::maybe_restore_plugin_media( $messages );
			self::maybe_normalize_content_manifest_from_wordpress( $messages );
		}

		return array(
			'ok' => true,
			'done' => $done,
			'next_offset' => $next_offset,
			'updated_in_batch' => $updated_in_batch,
			'processed_in_batch' => $processed_in_batch,
			'total_jobs' => $total_jobs,
			'messages' => $messages,
		);
	}

	/**
	 * Import plugin files into WordPress. Updates post bodies when on-disk markup differs. Rewrites each scanned
	 * plugin HTML file on disk to canonical backup form when raw bytes differ. Replaces tags, categories, and
	 * manifest publication time when a manifest row applies (rows without `date_gmt` stamp “now” except for posts
	 * already published before the restore). When restore finishes, regenerates content-manifest.json from WordPress.
	 * Optional restore cleanup can remove posts with no on-disk file (see settings).
	 *
	 * Uses the same batch size as the Backup / Restore admin AJAX restore action.
	 *
	 * @param bool $confirmed User confirmed replace.
	 *
	 * @return array{ok:bool, messages:list<string>, updated:int}
	 */
	public static function restore_from_files( bool $confirmed ): array {
		if ( ! $confirmed ) {
			return array(
				'ok' => false,
				'messages' => array( __( 'Restore was not confirmed.', \ASC_AI_BOILER_TEXT_DOMAIN ) ),
				'updated' => 0,
			);
		}


		$messages = array();
		$updated = 0;
		$offset = 0;
		$done = false;

		while ( ! $done ) {
			$batch = self::run_restore_batch( $offset, true );
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
	private static function apply_manifest_title_slug_if_drifted(
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
		$manifest_title = self::manifest_title_for_create( $manifest_entry, $filename );
		if ( '' !== $manifest_title && $manifest_title !== (string) $post->post_title ) {
			$update['post_title'] = $manifest_title;
		}

		$file_slug = self::filename_to_slug( $filename );
		if ( '' !== $file_slug ) {
			$manifest_slug = isset( $manifest_entry['slug'] ) ? trim( (string) $manifest_entry['slug'] ) : '';
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
				__( 'Failed to update title or slug for %1$s: %2$s', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path,
				(string) $result->get_error_message()
			);
			return false;
		}

		$line = __( 'Updated title or slug from backup for %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
		$messages[] = sprintf( $line, $relative_path );

		return true;
	}

	/**
	 * Ensure {@see RegisterPartials::META_PARTIAL_KEY} matches the shell map for this file (fixes posts matched by slug only).
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
	private static function expected_partial_key_for_file( string $filename, ?array $manifest_entry = null ): string {
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
	 * Ensure {@see RegisterPartials::META_PARTIAL_KEY} is set and the post uses the boiler partial CPT.
	 *
	 * @param int $post_id Partial post ID.
	 * @param string $filename Plugin partial HTML basename.
	 * @param array<string, mixed>|null $manifest_entry Optional manifest row.
	 *
	 * @return bool True when post type or meta was updated.
	 */
	private static function ensure_partial_shell_meta( int $post_id, string $filename, ?array $manifest_entry = null ): bool {
		$expected_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' === $expected_key ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || RegisterPartials::POST_TYPE !== (string) $post->post_type ) {
			return false;
		}

		$changed = false;

		$current = trim( (string) get_post_meta( $post_id, RegisterPartials::META_PARTIAL_KEY, true ) );
		if ( $current !== $expected_key ) {
			update_post_meta( $post_id, RegisterPartials::META_PARTIAL_KEY, $expected_key );
			$changed = true;
		}

		if ( $changed ) {
			PartialStore::invalidate_cache();
		}

		return $changed;
	}

	/**
	 * Find a boiler partial post ({@see RegisterPartials::POST_TYPE}) for a manifest HTML file.
	 *
	 * Ignores manifest `post_id`; matches logical partial key and slug only.
	 *
	 * @param string $filename Basename under content/partials/.
	 * @param bool $published_only When true, only published posts match.
	 *
	 * @return WP_Post|null
	 */
	private static function find_post_for_partial_filename( string $filename, bool $published_only ): ?WP_Post {
		$manifest_entry = self::get_manifest_entry_for_file( SyncConfig::CONTENT_TYPE_PARTIALS, $filename, null );

		$partial_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' !== $partial_key ) {
			$post = PartialStore::get_post_by_partial_key( $partial_key, ! $published_only );
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
		$file_slug = self::filename_to_slug( $filename );
		if ( '' !== $file_slug ) {
			$slugs[] = $file_slug;
		}

		$seen_slugs = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $seen_slugs[ $slug ] ) ) {
				continue;
			}
			$seen_slugs[ $slug ] = true;

			$post = self::query_post_by_slug( RegisterPartials::POST_TYPE, $slug, $published_only );
			if ( null !== $post ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * @param string $post_type Post type slug.
	 * @param string $slug Post name (slug).
	 * @param bool $published_only When true, only published posts match.
	 *
	 * @return WP_Post|null
	 */
	private static function query_post_by_slug( string $post_type, string $slug, bool $published_only ): ?WP_Post {
		$statuses = $published_only ? array( 'publish' ) : array( 'publish', 'draft', 'pending', 'future', 'private' );
		$query = new WP_Query(
			array(
				'post_type' => $post_type,
				'post_status' => $statuses,
				'name' => $slug,
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
			)
		);

		$post = null;
		if ( $query->have_posts() && $query->posts[0] instanceof WP_Post ) {
			$post = $query->posts[0];
		}
		wp_reset_postdata();

		return $post;
	}

	private static function repair_shell_partial_meta_if_needed( string $type_key, string $filename, int $post_id ): bool {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
			return false;
		}

		$manifest_entry = self::get_manifest_entry_for_file( $type_key, $filename, null );
		$expected_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' === $expected_key ) {
			return false;
		}

		return self::ensure_partial_shell_meta( $post_id, $filename, $manifest_entry );
	}

	/**
	 * Import one partial from content-manifest.json into {@see RegisterPartials::POST_TYPE}.
	 *
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return bool True when a post was created or updated.
	 */
	private static function restore_one_partial_from_manifest( string $filename, array &$messages ): bool {
		$type_key = SyncConfig::CONTENT_TYPE_PARTIALS;
		$relative_path = self::relative_content_type_file_path( $type_key, $filename );
		$manifest_entry = self::get_manifest_entry_for_file( $type_key, $filename, null );
		if ( null === $manifest_entry ) {
			return false;
		}

		$partial_key = self::expected_partial_key_for_file( $filename, $manifest_entry );
		if ( '' === $partial_key ) {
			return false;
		}

		$markup = '';
		if ( is_file( self::resolve_content_file_path( $type_key, $filename ) ) ) {
			$markup = self::normalize_markup_for_storage( self::read_content_markup( $type_key, $filename ) );
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
				__( 'Created partial for %s.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path
			);
		}

		if ( self::ensure_partial_shell_meta( $post_id, $filename, $manifest_entry ) ) {
			$changed = true;
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Set partial key for %s.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $changed;
		}

		if ( '' !== trim( $markup ) && ! self::markup_is_in_sync( $markup, (string) $post->post_content ) ) {
			$update = array(
				'ID' => $post_id,
				'post_status' => 'publish',
				'post_content' => wp_slash( $markup ),
			);
			$manifest_title = self::manifest_title_for_create( $manifest_entry, $filename );
			if ( '' !== $manifest_title ) {
				$update['post_title'] = $manifest_title;
			}
			$timestamp_fields = self::manifest_timestamp_fields_for_update( $post, $manifest_entry, $existed_before );
			if ( array() !== $timestamp_fields ) {
				$update = array_merge( $update, $timestamp_fields );
			}

			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				$messages[] = sprintf(
					/* translators: 1: relative path, 2: error */
					__( 'Failed to update %1$s: %2$s', \ASC_AI_BOILER_TEXT_DOMAIN ),
					$relative_path,
					$result->get_error_message()
				);
				return false;
			}

			$changed = true;
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Restored %s.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path
			);
		}

		if ( null !== $manifest_entry ) {
			if ( self::apply_manifest_title_slug_if_drifted( $post_id, $type_key, $filename, $manifest_entry, $relative_path, $messages ) ) {
				$changed = true;
			}
		}

		if ( '' !== $markup && is_file( self::resolve_content_file_path( $type_key, $filename ) ) ) {
			if ( self::maybe_normalize_plugin_file_on_disk( $type_key, $filename, $markup, $messages ) ) {
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Create a published {@see RegisterPartials::POST_TYPE} post from a manifest partial row.
	 *
	 * @param string $filename HTML basename.
	 * @param array<string, mixed> $manifest_entry Manifest row.
	 * @param string $partial_key Logical partial key for post meta.
	 *
	 * @return WP_Post|null
	 */
	private static function create_partial_post_from_manifest( string $filename, array $manifest_entry, string $partial_key ): ?WP_Post {
		$title = self::manifest_title_for_create( $manifest_entry, $filename );
		if ( '' === $title ) {
			$title = self::title_fallback_from_slug( str_replace( '_', '-', $partial_key ) );
		}
		if ( '' === $title ) {
			return null;
		}

		$slug = self::filename_to_slug( $filename );
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
				'post_type' => RegisterPartials::POST_TYPE,
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

		update_post_meta( $post_id, RegisterPartials::META_PARTIAL_KEY, $partial_key );
		PartialStore::invalidate_cache();

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}

	/**
	 * Apply one on-disk file to WordPress when markup differs, apply manifest taxonomies and publication time when present.
	 *
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return bool True when WordPress post content, taxonomies, publication time, title, or slug were updated.
	 */
	private static function restore_one_file( string $type_key, string $filename, array &$messages ): bool {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return self::restore_one_partial_from_manifest( $filename, $messages );
		}

		$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );

		$absolute = self::resolve_content_file_path( $type_key, $filename );
		if ( ! is_file( $absolute ) ) {
			return false;
		}

		$markup = self::normalize_markup_for_storage( ContentSync::read_content_markup( $type_key, $filename ) );

		$post = self::find_post_for_filename( $type_key, $filename, false );
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
				__( 'Set shell partial key for %s.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path
			);
		}

		$manifest_entry = self::get_manifest_entry_for_file( $type_key, $filename, $post );

		$content_changed = false;

		if ( ! self::markup_is_in_sync( $markup, (string) $post->post_content ) ) {
			$update = array(
				'ID' => $post_id,
				'post_content' => wp_slash( $markup ),
			);
			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$update['post_status'] = 'publish';
			}

			if ( null !== $manifest_entry ) {
				$manifest_title = self::manifest_title_for_create( $manifest_entry, $filename );
				if ( '' !== $manifest_title ) {
					$update['post_title'] = $manifest_title;
				}

				$timestamp_fields = self::manifest_timestamp_fields_for_update( $post, $manifest_entry, $existed_before );
				if ( array() !== $timestamp_fields ) {
					$update = array_merge( $update, $timestamp_fields );
				}
			}

			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				$messages[] = sprintf(
					/* translators: 1: relative path, 2: error */
					__( 'Failed to update %1$s: %2$s', \ASC_AI_BOILER_TEXT_DOMAIN ),
					$relative_path,
					$result->get_error_message()
				);
				return false;
			}

			$content_changed = true;
			$restored_line = __( 'Restored %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
			$messages[] = sprintf( $restored_line, $relative_path );
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
			$taxonomy_changed = self::apply_manifest_taxonomies_from_manifest_entry(
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
				$timestamps_changed = self::apply_manifest_timestamps_from_entry(
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
			$tax_line = __( 'Updated tags/categories from backup for %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
			$messages[] = sprintf( $tax_line, $relative_path );
		}

		if ( $timestamps_changed && ! $content_changed ) {
			$time_line = __( 'Updated publication time from backup for %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
			$messages[] = sprintf( $time_line, $relative_path );
		}

		if ( ! $existed_before && ! $content_changed && ! $title_slug_changed && ! $taxonomy_changed && ! $timestamps_changed ) {
			$messages[] = sprintf(
				/* translators: %s: relative plugin path */
				__( 'Created %s in WordPress from plugin files.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path
			);
		}

		$file_normalized = self::maybe_normalize_plugin_file_on_disk( $type_key, $filename, $markup, $messages );

		return $content_changed || $title_slug_changed || $taxonomy_changed || $timestamps_changed
			|| ! $existed_before
			|| $shell_meta_repaired
			|| $file_normalized;
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
				$post = self::find_post_for_filename( $type_key, $filename );
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
	 * Read-only: compare plugin HTML, backup manifest metadata (same slice as backup “manifest refresh”), publication date,
	 * and whether on-disk HTML needs whitespace normalization to match backup canonical form.
	 *
	 * @return array{
	 *   in_sync: bool,
	 *   differences: list<array{
	 *     relative_path: string,
	 *     issues: list<string>,
	 *     suggestion: string,
	 *     suggestion_note: string,
	 *     file_modified_gmt: string,
	 *     wp_modified_gmt: string
	 *   }>,
	 *   checked_at: string
	 * }
	 */
	public static function run_detect_content_differences(): array {
		self::invalidate_content_manifest_cache();

		$differences = array();
		$orphan_paths = array();

		foreach ( self::collect_orphan_wordpress_posts() as $row ) {
			$post_id = (int) $row['post_id'];
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$relative_path = (string) $row['relative_path'];
			$wp_modified = get_post_modified_time( 'c', true, $post );
			$wp_iso = is_string( $wp_modified ) ? $wp_modified : '';

			$differences[] = array(
				'relative_path' => $relative_path,
				'issues' => array(
					sprintf(
						/* translators: 1: post title, 2: relative plugin path */
						__( 'Published WordPress content "%1$s" has no matching plugin file on disk (%2$s).', \ASC_AI_BOILER_TEXT_DOMAIN ),
						get_the_title( $post ),
						$relative_path
					),
				),
				'suggestion' => 'backup',
				'suggestion_note' => __( 'Suggested: Backup to plugin files to write the missing HTML.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				'file_modified_gmt' => '',
				'wp_modified_gmt' => $wp_iso,
			);
		}

		foreach ( self::collect_orphan_plugin_files() as $row ) {
			$type_key = (string) $row['type'];
			$filename = (string) $row['filename'];
			$relative_path = (string) $row['relative_path'];
			$orphan_paths[ $relative_path ] = true;

			$absolute = ContentSync::get_content_type_directory( $type_key ) . $filename;
			$ft = is_file( $absolute ) ? filemtime( $absolute ) : false;
			$file_iso = false !== $ft ? gmdate( 'c', (int) $ft ) : '';

			$differences[] = array(
				'relative_path' => $relative_path,
				'issues' => array(
					__( 'Plugin backup file exists; no matching published WordPress content for this file.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				),
				'suggestion' => 'restore',
				'suggestion_note' => __( 'Suggested: Restore from plugin files (or publish matching content in WordPress).', \ASC_AI_BOILER_TEXT_DOMAIN ),
				'file_modified_gmt' => $file_iso,
				'wp_modified_gmt' => '',
			);
		}

		foreach ( self::collect_restore_file_jobs() as $job ) {
			$type_key = (string) $job['type'];
			$filename = (string) $job['filename'];
			$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );

			if ( isset( $orphan_paths[ $relative_path ] ) ) {
				continue;
			}

			$absolute = ContentSync::get_content_type_directory( $type_key ) . $filename;
			if ( ! is_file( $absolute ) ) {
				continue;
			}

			$post = self::find_post_for_filename( $type_key, $filename, true );
			if ( null === $post ) {
				continue;
			}

			$markup = ContentSync::read_content_markup( $type_key, $filename );
			$body_differs = ! self::markup_is_in_sync( $markup, (string) $post->post_content );
			$issues = array();

			if ( $body_differs ) {
				$issues[] = __( 'Post body HTML differs from the plugin file (normalized comparison).', \ASC_AI_BOILER_TEXT_DOMAIN );
			}

			if ( ! $body_differs && self::plugin_file_needs_whitespace_normalization( $absolute, (string) $post->post_content ) ) {
				$issues[] = __( 'Whitespace needs to be normalized.', \ASC_AI_BOILER_TEXT_DOMAIN );
			}

			$manifest_entry = self::get_manifest_entry_for_file( $type_key, $filename, $post );
			$issues = array_merge( $issues, self::describe_paired_manifest_drift_for_detect( $type_key, $post, $manifest_entry ) );

			if ( array() === $issues ) {
				continue;
			}

			$file_iso = '';
			$wp_iso = '';

			if ( ! $body_differs ) {
				$manifest_metadata_drift = self::backup_manifest_row_differs_from_post( $type_key, $filename, $post );
				$file_whitespace_drift = self::plugin_file_needs_whitespace_normalization( $absolute, (string) $post->post_content );
				if ( $file_whitespace_drift || $manifest_metadata_drift ) {
					$suggestion = 'restore';
					if ( $file_whitespace_drift && $manifest_metadata_drift ) {
						$suggestion_note = __(
							'Suggested: Restore from plugin files — normalizes plugin HTML and content-manifest.json on disk.',
							\ASC_AI_BOILER_TEXT_DOMAIN
						);
					} elseif ( $file_whitespace_drift ) {
						$suggestion_note = __(
							'Suggested: Restore from plugin files — rewrites plugin HTML to canonical backup form on disk.',
							\ASC_AI_BOILER_TEXT_DOMAIN
						);
					} else {
						$suggestion_note = __(
							'Suggested: Restore from plugin files — rewrites content-manifest.json to canonical backup form on disk.',
							\ASC_AI_BOILER_TEXT_DOMAIN
						);
					}
				} else {
					$suggestion = 'backup';
					$suggestion_note = __(
						'Suggested: Backup to plugin files — updates content-manifest.json. Plugin HTML already matches WordPress.',
						\ASC_AI_BOILER_TEXT_DOMAIN
					);
				}
			} else {
				$ft = filemtime( $absolute );
				$file_ts = false !== $ft ? (int) $ft : 0;
				$wp_ts = (int) get_post_modified_time( 'U', true, $post );
				$file_iso = $file_ts > 0 ? gmdate( 'c', $file_ts ) : '';
				$wp_iso = $wp_ts > 0 ? gmdate( 'c', $wp_ts ) : '';

				$suggestion = 'unclear';
				if ( $file_ts > $wp_ts ) {
					$suggestion = 'restore';
					$suggestion_note = sprintf(
						/* translators: 1: file modified (ISO 8601), 2: WordPress modified (ISO 8601) */
						__( 'Suggested: Restore from plugin files — the backup file is newer (%1$s) than WordPress (%2$s).', \ASC_AI_BOILER_TEXT_DOMAIN ),
						$file_iso,
						$wp_iso
					);
				} elseif ( $wp_ts > $file_ts ) {
					$suggestion = 'backup';
					$suggestion_note = sprintf(
						/* translators: 1: WordPress modified (ISO 8601), 2: file modified (ISO 8601) */
						__( 'Suggested: Backup to plugin files — WordPress is newer (%1$s) than the backup file (%2$s).', \ASC_AI_BOILER_TEXT_DOMAIN ),
						$wp_iso,
						$file_iso
					);
				} else {
					$suggestion_note = sprintf(
						/* translators: %s: ISO 8601 datetime (both sides match) */
						__( 'Post body or manifest fields differ, but last modified times match (%s). Review and choose backup or restore.', \ASC_AI_BOILER_TEXT_DOMAIN ),
						$file_iso
					);
				}
			}

			$differences[] = array(
				'relative_path' => $relative_path,
				'issues' => $issues,
				'suggestion' => $suggestion,
				'suggestion_note' => $suggestion_note,
				'file_modified_gmt' => $file_iso,
				'wp_modified_gmt' => $wp_iso,
			);
		}

		usort(
			$differences,
			static function ( array $a, array $b ): int {
				return strcmp( (string) $a['relative_path'], (string) $b['relative_path'] );
			}
		);

		return array(
			'in_sync' => array() === $differences,
			'differences' => $differences,
			'checked_at' => gmdate( 'c' ),
		);
	}

	/**
	 * Manifest drift for a paired file/post: publication date plus the same metadata slice backup uses for “manifest refresh” ({@see self::manifest_row_metadata_snapshot_for_compare()}).
	 *
	 * Keeps Detect aligned with skip-write manifest updates (e.g. seeded partials where `post_name` need not match the `.html` basename).
	 *
	 * @param string $type_key Content type key.
	 * @param WP_Post $post Post.
	 * @param array<string, mixed>|null $manifest_entry Row when present.
	 *
	 * @return list<string>
	 */
	private static function describe_paired_manifest_drift_for_detect(
		string $type_key,
		WP_Post $post,
		?array $manifest_entry
	): array {
		if ( null === $manifest_entry ) {
			return array();
		}

		$lines = self::describe_publication_drift_for_detect( $post, $manifest_entry );

		$desired = self::build_backup_manifest_row_from_post( $type_key, $post );
		if ( null === $desired ) {
			return $lines;
		}

		if ( self::manifest_row_metadata_snapshot_for_compare( $desired )
			!== self::manifest_row_metadata_snapshot_for_compare( $manifest_entry ) ) {
			$lines[] = __(
				'content-manifest.json metadata for this file does not match WordPress (title, slug, filename, categories, or tags).',
				\ASC_AI_BOILER_TEXT_DOMAIN
			);
		}

		return $lines;
	}

	/**
	 * Publication time drift for detect when manifest lists date_gmt (no writes).
	 *
	 * @param WP_Post $post Post.
	 * @param array<string, mixed>|null $manifest_entry Row when present.
	 *
	 * @return list<string>
	 */
	private static function describe_publication_drift_for_detect( WP_Post $post, ?array $manifest_entry ): array {
		if ( null === $manifest_entry ) {
			return array();
		}

		$date_rfc = isset( $manifest_entry['date_gmt'] ) ? (string) $manifest_entry['date_gmt'] : '';
		$pub_mysql = self::manifest_rfc3339_to_mysql_gmt( $date_rfc );
		if ( '' === $pub_mysql ) {
			return array();
		}

		if ( self::post_publication_gmt_matches_mysql( $post, $pub_mysql ) ) {
			return array();
		}

		return array( __( 'Publication date (GMT) differs between WordPress and the backup manifest.', \ASC_AI_BOILER_TEXT_DOMAIN ) );
	}

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
	private static function run_backup_batch( int $type_index, int $post_offset ): array {
		$type_entries = ContentSyncProfile::sync_types();
		$type_keys = array_keys( $type_entries );
		$num_types = count( $type_keys );
		$batch_size = SyncConfig::CONTENT_SYNC_BATCH_SIZE;

		$type_index = max( 0, $type_index );
		$post_offset = max( 0, $post_offset );

		if ( 0 === $type_index && 0 === $post_offset ) {
			self::invalidate_content_manifest_cache();
		}

		$messages = array();
		$updated_in_batch = 0;
		$manifest_metadata_refreshed_in_batch = 0;
		$ti = $type_index;
		$po = $post_offset;
		$remaining = $batch_size;

		while ( $remaining > 0 && $ti < $num_types ) {
			$type_key = $type_keys[ $ti ];
			$type_config = $type_entries[ $type_key ];
			$posts = self::query_posts_for_type( $type_config['post_type'] );
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

				$filename = self::derive_filename_for_post( $type_key, $post );
				if ( '' === $filename ) {
					$remaining--;
					continue;
				}

				$relative_path = ContentSync::relative_content_type_file_path( $type_key, $filename );
				$markup = (string) $post->post_content;
				$absolute = ContentSync::get_content_type_directory( $type_key ) . $filename;
				$skip_write = is_file( $absolute )
					&& self::markup_is_in_sync( ContentSync::read_content_markup( $type_key, $filename ), $markup );
				if ( $skip_write ) {
					if ( self::maybe_normalize_plugin_file_on_disk( $type_key, $filename, wp_unslash( $markup ), $messages ) ) {
						++$updated_in_batch;
					}

					if ( self::backup_manifest_row_differs_from_post( $type_key, $filename, $post ) ) {
						++$manifest_metadata_refreshed_in_batch;
						$messages[] = sprintf(
							/* translators: %s: relative plugin path */
							__( 'Manifest metadata will refresh for %s (HTML already matched on disk).', \ASC_AI_BOILER_TEXT_DOMAIN ),
							$relative_path
						);
					}
					$remaining--;
					continue;
				}

				$saved = ContentSync::write_content_markup( $type_key, $filename, $markup );
				if ( $saved ) {
					++$updated_in_batch;
					$exported_line = __( 'Backed up %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
					$messages[] = sprintf( $exported_line, $relative_path );
				} else {
					$messages[] = sprintf(
						/* translators: %s: relative path */
						__( 'Failed to write %s. Check file permissions.', \ASC_AI_BOILER_TEXT_DOMAIN ),
						$relative_path
					);
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
			if ( SyncConfig::is_backup_cleanup() ) {
				self::delete_orphan_plugin_files( $messages );
			}
			self::maybe_backup_plugin_media( $messages );
			self::write_content_backup_manifest( $messages );
		}

		return array(
			'ok' => true,
			'done' => $done,
			'type_index' => $ti,
			'post_offset' => $po,
			'updated_in_batch' => $updated_in_batch,
			'manifest_metadata_refreshed_in_batch' => $manifest_metadata_refreshed_in_batch,
			'messages' => $messages,
		);
	}

	/**
	 * Export all published WordPress content to plugin files, rewrite content-manifest.json from the
	 * database, and delete plugin HTML with no matching published content.
	 *
	 * Uses the same batch stepping as the Backup / Restore admin AJAX backup action.
	 *
	 * @return array{ok:bool, messages:list<string>, updated:int, manifest_metadata_refreshed:int}
	 */
	public static function backup_to_files(): array {
		$messages = array();
		$updated = 0;
		$manifest_metadata_refreshed = 0;
		$type_index = 0;
		$post_offset = 0;
		$done = false;

		while ( ! $done ) {
			$batch = self::run_backup_batch( $type_index, $post_offset );
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
	 * Build category and post_tag payloads for a manifest row (slug + name per term).
	 *
	 * @return array<string, list<array{slug:string, name:string}>>
	 */
	private static function manifest_taxonomy_lists_for_post( WP_Post $post ): array {
		$out = array();
		$post_type = (string) $post->post_type;

		if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
			$terms = get_the_terms( (int) $post->ID, 'category' );
			if ( is_array( $terms ) && $terms !== array() ) {
				$list = array();
				foreach ( $terms as $term ) {
					if ( ! $term instanceof WP_Term ) {
						continue;
					}
					$list[] = array(
						'slug' => (string) $term->slug,
						'name' => (string) $term->name,
					);
				}
				if ( $list !== array() ) {
					$out['categories'] = $list;
				}
			}
		}

		if ( is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
			$terms = get_the_terms( (int) $post->ID, 'post_tag' );
			if ( is_array( $terms ) && $terms !== array() ) {
				$list = array();
				foreach ( $terms as $term ) {
					if ( ! $term instanceof WP_Term ) {
						continue;
					}
					$list[] = array(
						'slug' => (string) $term->slug,
						'name' => (string) $term->name,
					);
				}
				if ( $list !== array() ) {
					$out['tags'] = $list;
				}
			}
		}

		return $out;
	}

	/**
	 * Apply manifest tags/categories: replace WordPress terms when the manifest lists each taxonomy.
	 *
	 * @param int $post_id Post ID.
	 * @param string $post_type Post type slug.
	 * @param array<string, mixed> $manifest_entry Row from content-manifest.json.
	 * @param list<string> $messages Messages accumulator.
	 * @param string $relative_path Plugin-relative path for log lines.
	 *
	 * @return bool True when taxonomy associations changed.
	 */
	private static function apply_manifest_taxonomies_from_manifest_entry(
		int $post_id,
		string $post_type,
		array $manifest_entry,
		array &$messages,
		string $relative_path
	): bool {
		$changed = false;

		if ( isset( $manifest_entry['tags'] ) && is_array( $manifest_entry['tags'] ) ) {
			if ( is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
				if ( self::merge_manifest_taxonomy_term_rows(
					$post_id,
					'post_tag',
					$manifest_entry['tags'],
					$messages,
					$relative_path
				) ) {
					$changed = true;
				}
			}
		}

		if ( isset( $manifest_entry['categories'] ) && is_array( $manifest_entry['categories'] ) ) {
			if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
				if ( self::merge_manifest_taxonomy_term_rows(
					$post_id,
					'category',
					$manifest_entry['categories'],
					$messages,
					$relative_path
				) ) {
					$changed = true;
				}
			}
		}

		return $changed;
	}

	/**
	 * Resolve manifest term rows to term IDs and set the post’s terms to exactly that set (empty list clears).
	 *
	 * @param int $post_id Post ID.
	 * @param string $taxonomy Taxonomy slug (e.g. post_tag, category).
	 * @param array<int, mixed> $rows Manifest term rows (slug + name).
	 * @param list<string> $messages Messages accumulator.
	 * @param string $relative_path For log lines.
	 *
	 * @return bool True when associations changed.
	 */
	private static function merge_manifest_taxonomy_term_rows(
		int $post_id,
		string $taxonomy,
		array $rows,
		array &$messages,
		string $relative_path
	): bool {
		$term_ids_to_add = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$slug = isset( $row['slug'] ) ? sanitize_title( (string) $row['slug'] ) : '';
			if ( '' === $slug ) {
				continue;
			}

			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				$name = $slug;
			}

			$term = get_term_by( 'slug', $slug, $taxonomy );
			$term_id = 0;

			if ( $term instanceof WP_Term ) {
				$term_id = (int) $term->term_id;
			} else {
				$ins = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'slug' => $slug,
					)
				);

				if ( is_wp_error( $ins ) ) {
					if ( 'term_exists' === $ins->get_error_code() ) {
						$data = $ins->get_error_data();
						if ( is_int( $data ) ) {
							$term_id = $data;
						} elseif ( is_array( $data ) && isset( $data['term_id'] ) ) {
							$term_id = (int) $data['term_id'];
						}
					}
					if ( $term_id <= 0 ) {
						$messages[] = sprintf(
							/* translators: 1: taxonomy, 2: term slug, 3: relative path, 4: error message */
							__( 'Could not add %1$s term "%2$s" for %3$s: %4$s', \ASC_AI_BOILER_TEXT_DOMAIN ),
							$taxonomy,
							$slug,
							$relative_path,
							$ins->get_error_message()
						);
						continue;
					}
				} else {
					if ( is_array( $ins ) && isset( $ins['term_id'] ) ) {
						$term_id = (int) $ins['term_id'];
					}
				}
			}

			if ( $term_id > 0 ) {
				$term_ids_to_add[] = $term_id;
			}
		}

		$target_ids = array_values( array_unique( $term_ids_to_add ) );

		$existing = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $existing ) ) {
			$messages[] = sprintf(
				/* translators: 1: taxonomy, 2: relative path, 3: error message */
				__( 'Could not read %1$s on %2$s: %3$s', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$taxonomy,
				$relative_path,
				$existing->get_error_message()
			);
			return false;
		}

		$existing_ids = array_map( 'intval', $existing );
		sort( $existing_ids, SORT_NUMERIC );
		$compare_target = $target_ids;
		sort( $compare_target, SORT_NUMERIC );
		if ( $existing_ids === $compare_target ) {
			return false;
		}

		$replace_result = wp_set_object_terms( $post_id, $target_ids, $taxonomy, false );
		if ( is_wp_error( $replace_result ) ) {
			$messages[] = sprintf(
				/* translators: 1: taxonomy, 2: relative path, 3: error message */
				__( 'Could not set %1$s on %2$s: %3$s', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$taxonomy,
				$relative_path,
				$replace_result->get_error_message()
			);
			return false;
		}

		return true;
	}

	/**
	 * One manifest types[] row as written during backup (portable across installs: no post_id or modified_gmt).
	 *
	 * @param string $type_key Type key (pages, partials, etc.).
	 * @param WP_Post $post Published post.
	 *
	 * @return array<string, mixed>|null Null when no on-disk filename.
	 */
	private static function build_backup_manifest_row_from_post( string $type_key, WP_Post $post ): ?array {
		$fresh = get_post( (int) $post->ID );
		if ( $fresh instanceof WP_Post ) {
			$post = $fresh;
		}

		$filename = self::derive_filename_for_post( $type_key, $post );
		if ( '' === $filename ) {
			return null;
		}

		$published = get_post_time( 'c', true, $post );

		$post_type = (string) $post->post_type;
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$post_type = RegisterPartials::POST_TYPE;
		}

		$row = array(
			'post_type' => $post_type,
			'title' => (string) $post->post_title,
				'slug' => (string) $post->post_name,
				'filename' => $filename,
				'date_gmt' => is_string( $published ) ? $published : '',
		);

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$partial_key = trim( (string) get_post_meta( (int) $post->ID, RegisterPartials::META_PARTIAL_KEY, true ) );
			if ( '' !== $partial_key ) {
				$row['partial_key'] = $partial_key;
			}
		}

		return array_merge( $row, self::manifest_taxonomy_lists_for_post( $post ) );
	}

	/**
	 * Sort manifest-style category/tag rows by term slug for stable comparison.
	 *
	 * @param list<array<string, mixed>> $categories Term rows.
	 * @param list<array<string, mixed>> $tags Term rows.
	 *
	 * @return array{categories: list<array{slug: string, name: string}>, tags: list<array{slug: string, name: string}>}
	 */
	private static function manifest_taxonomy_columns_sorted( array $categories, array $tags ): array {
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
					'slug' => self::normalize_manifest_compare_scalar( isset( $item['slug'] ) ? (string) $item['slug'] : '' ),
					'name' => self::normalize_manifest_compare_scalar( isset( $item['name'] ) ? (string) $item['name'] : '' ),
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
	private static function normalize_manifest_compare_scalar( string $value ): string {
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
	private static function manifest_row_metadata_snapshot_for_compare( array $row ): array {
		$tax = self::manifest_taxonomy_columns_sorted(
			isset( $row['categories'] ) && is_array( $row['categories'] ) ? $row['categories'] : array(),
			isset( $row['tags'] ) && is_array( $row['tags'] ) ? $row['tags'] : array()
		);

		$post_type = isset( $row['post_type'] ) ? (string) $row['post_type'] : '';
		$post_type = trim( $post_type );

		return array(
			'post_type' => self::normalize_manifest_compare_scalar( $post_type ),
			'title' => self::normalize_manifest_compare_scalar( isset( $row['title'] ) ? (string) $row['title'] : '' ),
			'slug' => self::normalize_manifest_compare_scalar( isset( $row['slug'] ) ? (string) $row['slug'] : '' ),
			'filename' => self::normalize_manifest_compare_scalar( isset( $row['filename'] ) ? (string) $row['filename'] : '' ),
			'categories' => $tax['categories'],
			'tags' => $tax['tags'],
		);
	}

	/**
	 * True when the on-disk manifest row for this file does not match post fields that backup would
	 * rewrite aside from publication/modified timestamps on disk (ignored for this comparison; see {@see self::describe_publication_drift_for_detect()}).
	 *
	 * @param string $type_key Type key.
	 * @param string $filename Basename.
	 * @param WP_Post $post Post.
	 *
	 * @return bool
	 */
	private static function backup_manifest_row_differs_from_post( string $type_key, string $filename, WP_Post $post ): bool {
		$desired = self::build_backup_manifest_row_from_post( $type_key, $post );
		if ( null === $desired ) {
			return false;
		}

		$entry = self::get_manifest_entry_for_file( $type_key, $filename, $post );
		$manifest_path = ContentSync::get_content_manifest_path();
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
	 * Called when a backup run completes ({@see self::run_backup_batch()}) and when a restore run finishes
	 * ({@see self::maybe_normalize_content_manifest_from_wordpress()}).
	 *
	 * @return bool True when the file was written successfully.
	 */
	private static function write_content_backup_manifest( array &$messages ): bool {
		ContentSync::ensure_content_directories_exist();

		$types_out = array();
		foreach ( ContentSyncProfile::sync_types() as $type_key => $type_config ) {
			$entries_by_filename = array();
			foreach ( self::query_posts_for_type( $type_config['post_type'] ) as $post ) {
				$row = self::build_backup_manifest_row_from_post( $type_key, $post );
				if ( null === $row ) {
					continue;
				}
				$fn = isset( $row['filename'] ) ? (string) $row['filename'] : '';
				if ( '' === $fn ) {
					continue;
				}
				$canonical = self::find_post_for_filename( $type_key, $fn, true );
				if ( null === $canonical || (int) $canonical->ID !== (int) $post->ID ) {
					continue;
				}
				$entries_by_filename[ $fn ] = $row;
			}
			ksort( $entries_by_filename, SORT_STRING );
			$types_out[ $type_key ] = array_values( $entries_by_filename );
		}

		$payload = array(
			'manifest_version' => 1,
			'exported_at' => gmdate( 'c' ),
			'types' => $types_out,
			'media' => ContentMediaSync::build_manifest_media_rows_for_manifest(),
			'media_bindings' => ContentMediaSync::build_manifest_media_bindings_for_backup(),
		);

		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}

		$json = wp_json_encode( $payload, $flags );
		if ( false === $json ) {
			return false;
		}

		$written = file_put_contents( ContentSync::get_content_manifest_path(), $json, LOCK_EX );
		if ( false !== $written ) {
			self::invalidate_content_manifest_cache();
		}
		return false !== $written;
	}

	/**
	 * Drop cached manifest types and version (call after manifest file changes on disk).
	 *
	 * @return void
	 */
	private static function invalidate_content_manifest_cache(): void {
		self::$content_manifest_types = null;
		ContentSyncProfile::invalidate_cache();
	}

	/**
	 * Normalize one manifest row after JSON decode.
	 *
	 * @param string $type_key Manifest `types` bucket key (e.g. partials, pages).
	 * @param array<string, mixed> $entry Raw row.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize_manifest_entry( string $type_key, array $entry ): array {
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
	 * Load and cache the `types` section of content-manifest.json.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function load_content_manifest_types(): array {
		if ( null !== self::$content_manifest_types ) {
			return self::$content_manifest_types;
		}

		$path = ContentSync::get_content_manifest_path();
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
	private static function dedupe_manifest_type_rows_last_filename_wins( array $rows ): array {
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
	 * Manifest rows for a filename: the primary `types` bucket when it has any match; otherwise mis-keyed buckets.
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Basename.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function collect_manifest_entries_for_filename( string $type_key, string $filename ): array {
		$types = self::load_content_manifest_types();
		$type_config = ContentSyncProfile::sync_types()[ $type_key ] ?? null;
		$expected_pt = is_array( $type_config ) ? (string) $type_config['post_type'] : '';
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
				$mpt = isset( $entry['post_type'] ) ? (string) $entry['post_type'] : '';
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
	 * When $for_post is set, prefers the row whose metadata snapshot matches what backup would write for that post
	 * (avoids stale duplicates). Otherwise returns the last matching row in file order.
	 *
	 * @param string $type_key Content type key (e.g. pages).
	 * @param string $filename Basename (e.g. about-us.html).
	 * @param WP_Post|null $for_post Canonical post for this file, when known.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function get_manifest_entry_for_file( string $type_key, string $filename, ?WP_Post $for_post = null ): ?array {
		$candidates = self::collect_manifest_entries_for_filename( $type_key, $filename );
		$n = count( $candidates );
		if ( 0 === $n ) {
			return null;
		}

		if ( $for_post instanceof WP_Post ) {
			$desired = self::build_backup_manifest_row_from_post( $type_key, $for_post );
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
	private static function manifest_rfc3339_to_mysql_gmt( string $rfc3339 ): string {
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
	private static function post_publication_gmt_matches_mysql( WP_Post $post, string $manifest_mysql_gmt ): bool {
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
	 * already published (`post_status` publish) before this restore (`$existed_before` true), so backup rows that
	 * omit the field do not wipe an existing publication date.
	 *
	 * @param WP_Post $post Post before update.
	 * @param array<string, mixed> $manifest_entry Manifest row.
	 * @param bool $existed_before True when the post existed in WordPress before this import job started.
	 *
	 * @return array<string, mixed> Non-empty when an update is needed (includes `edit_date` when applicable).
	 */
	private static function manifest_timestamp_fields_for_update( WP_Post $post, array $manifest_entry, bool $existed_before ): array {
		$out = array();

		$date_rfc = isset( $manifest_entry['date_gmt'] ) ? trim( (string) $manifest_entry['date_gmt'] ) : '';
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
	private static function apply_manifest_timestamps_from_entry(
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
			$err = __( 'Could not set publication time from backup for %1$s: %2$s', \ASC_AI_BOILER_TEXT_DOMAIN );
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
	private static function type_supports_manifest_driven_create( string $type_key ): bool {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return false;
		}

		return true;
	}

	/**
	 * When import has finished all file jobs, optionally remove published posts whose plugin file is gone.
	 *
	 * @param bool $confirmed Import was confirmed.
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return void
	 */
	private static function maybe_run_restore_cleanup( bool $confirmed, array &$messages ): void {
		if ( ! $confirmed ) {
			return;
		}

		if ( ! SyncConfig::is_restore_cleanup() ) {
			return;
		}

		self::delete_orphan_wordpress_posts_for_import( $messages );
	}

	/**
	 * Regenerate content-manifest.json from WordPress after restore (canonical backup form).
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool True when the manifest file was written.
	 */
	private static function maybe_normalize_content_manifest_from_wordpress( array &$messages ): bool {
		if ( ! self::write_content_backup_manifest( $messages ) ) {
			$messages[] = __(
				'Failed to normalize content-manifest.json. Check file permissions.',
				\ASC_AI_BOILER_TEXT_DOMAIN
			);

			return false;
		}

		$messages[] = __(
			'Normalized content-manifest.json to canonical backup form from WordPress.',
			\ASC_AI_BOILER_TEXT_DOMAIN
		);

		return true;
	}

	/**
	 * Import content/media/ into the WordPress media library and apply manifest bindings.
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return void
	 */
	private static function maybe_restore_plugin_media( array &$messages ): void {
		$result = ContentMediaSync::restore_from_plugin_files( $messages );
		if ( 0 === $result['processed'] ) {
			return;
		}

		if ( 0 === $result['updated'] ) {
			$messages[] = __(
				'Plugin media files already match the WordPress media library.',
				\ASC_AI_BOILER_TEXT_DOMAIN
			);
		}
	}

	/**
	 * Export bound attachments from WordPress to content/media/.
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return void
	 */
	private static function maybe_backup_plugin_media( array &$messages ): void {
		ContentMediaSync::backup_to_plugin_files( $messages );
	}

	/**
	 * Published synced posts whose derived on-disk filename does not exist under the content directory.
	 *
	 * @return list<array{post_id:int, type_key:string, filename:string, relative_path:string}>
	 */
	private static function collect_orphan_wordpress_posts(): array {
		$out = array();
		foreach ( ContentSyncProfile::sync_types() as $type_key => $type_config ) {
			$post_type = (string) $type_config['post_type'];
			foreach ( self::query_posts_for_type( $post_type ) as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$filename = self::derive_filename_for_post( $type_key, $post );
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

	/**
	 * Trash or delete published posts that no longer have a plugin HTML file (import cleanup).
	 *
	 * @param list<string> $messages Accumulated log lines.
	 *
	 * @return int Number of posts removed.
	 */
	private static function delete_orphan_wordpress_posts_for_import( array &$messages ): int {
		$orphans = self::collect_orphan_wordpress_posts();
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
					__( 'Skipped removing post "%1$s" (missing file %2$s): insufficient permission.', \ASC_AI_BOILER_TEXT_DOMAIN ),
					$title,
					$relative_path
				);
				continue;
			}

			$result = wp_delete_post( $post_id, false );
			if ( false !== $result ) {
				++$removed;
				$removed_line = __( 'Removed WordPress post "%1$s" (missing backup file %2$s, ID %3$d).', \ASC_AI_BOILER_TEXT_DOMAIN );
				$messages[] = sprintf(
					$removed_line,
					$title,
					$relative_path,
					$post_id
				);
			} else {
				$messages[] = sprintf(
					/* translators: 1: post title, 2: relative path */
					__( 'Could not remove post "%1$s" (missing file %2$s).', \ASC_AI_BOILER_TEXT_DOMAIN ),
					$title,
					$relative_path
				);
			}
		}

		return $removed;
	}

	/**
	 * Delete plugin HTML files that have no matching published post or page.
	 *
	 * @param list<string> $messages Accumulated log lines.
	 *
	 * @return int Number of files deleted.
	 */
	private static function delete_orphan_plugin_files( array &$messages ): int {
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
				$line = __( 'Deleted orphan content file %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
				$messages[] = sprintf( $line, $relative_path );
			} else {
				$fail_line = __( 'Could not delete orphan content file %s.', \ASC_AI_BOILER_TEXT_DOMAIN );
				$messages[] = sprintf( $fail_line, $relative_path );
			}
		}

		return $deleted;
	}

	/**
	 * AJAX: compare plugin files to WordPress published content (no writes).
	 *
	 * @return void
	 */
	public static function handle_ajax_detect_differences(): void {
		check_ajax_referer( self::nonce_action() );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to detect differences.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				),
				403
			);
		}

		$result = self::run_detect_content_differences();
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: run one import batch (see {@see self::run_restore_batch()}).
	 *
	 * @return void
	 */
	public static function handle_ajax_restore_batch(): void {
		check_ajax_referer( self::nonce_action() );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to run restore.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				),
				403
			);
		}

		$offset = isset( $_POST['offset'] ) ? absint( (string) wp_unslash( $_POST['offset'] ) ) : 0;
		$confirmed = SyncConfig::is_development_mode();
		if ( ! $confirmed && isset( $_POST['confirmed'] ) && '1' === (string) wp_unslash( $_POST['confirmed'] ) ) {
			$confirmed = true;
		}

		$result = self::run_restore_batch( $offset, $confirmed );
		if ( ! $result['ok'] ) {
			$fallback = __( 'Restore failed.', \ASC_AI_BOILER_TEXT_DOMAIN );
			$msg = $result['messages'][0] ?? $fallback;
			wp_send_json_error( array( 'message' => $msg ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: run one export batch (see {@see self::run_backup_batch()}).
	 *
	 * @return void
	 */
	public static function handle_ajax_backup_batch(): void {
		check_ajax_referer( self::nonce_action() );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to run backup.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				),
				403
			);
		}

		$type_index = isset( $_POST['type_index'] ) ? absint( (string) wp_unslash( $_POST['type_index'] ) ) : 0;
		$post_offset = isset( $_POST['post_offset'] ) ? absint( (string) wp_unslash( $_POST['post_offset'] ) ) : 0;

		$result = self::run_backup_batch( $type_index, $post_offset );
		if ( ! $result['ok'] ) {
			$batch_fail = __( 'Backup batch failed.', \ASC_AI_BOILER_TEXT_DOMAIN );
			wp_send_json_error( array( 'message' => $batch_fail ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Nonce action for static content sync AJAX.
	 *
	 * @return string
	 */
	public static function nonce_action(): string {
		return self::NONCE_ACTION;
	}

	/**
	 * Query `publish` posts for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return list<WP_Post>
	 */
	private static function query_posts_for_type( string $post_type ): array {
		$query = new WP_Query(
			array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
				'orderby' => 'title',
				'order' => 'ASC',
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}
		wp_reset_postdata();
		return $posts;
	}

	/**
	 * Derive the on-disk filename for a post.
	 *
	 * @param string $type_key Content type key.
	 * @param WP_Post $post Post.
	 *
	 * @return string
	 */
	private static function derive_filename_for_post( string $type_key, WP_Post $post ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$partial_key = trim( (string) get_post_meta( (int) $post->ID, RegisterPartials::META_PARTIAL_KEY, true ) );
			if ( '' !== $partial_key ) {
				foreach ( ContentSyncProfile::cpt_shell_map() as $filename => $seed_partial_key ) {
					if ( trim( $seed_partial_key ) === $partial_key ) {
						return $filename;
					}
				}
			}
			return self::slug_filename( $post );
		}

		if ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
			$front_id = (int) get_option( 'page_on_front' );
			if ( $front_id > 0 && $front_id === (int) $post->ID ) {
				return 'home.html';
			}
			return self::slug_filename( $post );
		}

		return self::slug_filename( $post );
	}

	/**
	 * Build `<slug>.html` for a post (empty when slug is missing).
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return string
	 */
	private static function slug_filename( WP_Post $post ): string {
		$slug = (string) $post->post_name;
		if ( '' === $slug ) {
			return '';
		}
		return $slug . '.html';
	}

	/**
	 * Title to assign when creating an entry from a seed (empty when no seed).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename.
	 *
	 * @return string
	 */
	private static function seed_title_for( string $type_key, string $filename ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$entry = self::get_manifest_entry_for_file( $type_key, $filename, null );
			if ( null !== $entry && isset( $entry['title'] ) ) {
				$t = trim( (string) $entry['title'] );
				if ( '' !== $t ) {
					return $t;
				}
			}
			$partial_key = self::expected_partial_key_for_file( $filename, $entry );
			if ( '' !== $partial_key ) {
				return self::title_fallback_from_slug( str_replace( '_', '-', $partial_key ) );
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
	private static function seed_partial_key_for( string $type_key, string $filename ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS !== $type_key ) {
			return '';
		}

		$entry = self::get_manifest_entry_for_file( $type_key, $filename, null );
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
	private static function seed_page_resolve_for( string $type_key, string $filename ): ?array {
		if ( SyncConfig::CONTENT_TYPE_PAGES !== $type_key ) {
			return null;
		}
		if ( ! isset( ContentSyncProfile::page_body_map()[ $filename ] ) ) {
			return null;
		}
		return ContentSyncProfile::page_body_map()[ $filename ];
	}

	/**
	 * Create a WordPress entry from a seed. Returns the new post (or null on failure).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename.
	 *
	 * @return WP_Post|null
	 */
	private static function create_post_from_seed( string $type_key, string $filename ): ?WP_Post {
		$title = self::seed_title_for( $type_key, $filename );
		if ( '' === $title ) {
			return null;
		}

		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$partial_key = self::seed_partial_key_for( $type_key, $filename );
			if ( '' === $partial_key ) {
				return null;
			}
			return PartialStore::create_shell_post_if_missing( $partial_key, $title );
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
	private static function create_post_from_manifest( string $type_key, string $filename ): ?WP_Post {
		$types = ContentSyncProfile::sync_types();
		if ( ! isset( $types[ $type_key ] ) ) {
			return null;
		}
		$type_config = $types[ $type_key ];
		if ( ! self::type_supports_manifest_driven_create( $type_key ) ) {
			return null;
		}

		$entry = self::get_manifest_entry_for_file( $type_key, $filename );
		if ( null === $entry ) {
			return null;
		}

		if ( self::find_post_for_filename( $type_key, $filename, false ) instanceof WP_Post ) {
			return null;
		}

		$title = self::manifest_title_for_create( $entry, $filename );
		if ( '' === $title ) {
			return null;
		}

		$slug = self::filename_to_slug( $filename );
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
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$post_type = RegisterPartials::POST_TYPE;
		}

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
	private static function create_post_minimal_from_disk( string $type_key, string $filename ): ?WP_Post {
		$types = ContentSyncProfile::sync_types();
		if ( ! isset( $types[ $type_key ] ) ) {
			return null;
		}
		$type_config = $types[ $type_key ];
		if ( ! self::type_supports_manifest_driven_create( $type_key ) ) {
			return null;
		}

		$slug = self::filename_to_slug( $filename );
		if ( '' === $slug ) {
			return null;
		}

		$title = self::title_fallback_from_slug( $slug );
		if ( '' === $title ) {
			return null;
		}

		if ( self::find_post_for_filename( $type_key, $filename, false ) instanceof WP_Post ) {
			return null;
		}

		$post_type = $type_config['post_type'];
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$post_type = RegisterPartials::POST_TYPE;
		}

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
			$manifest_entry = self::get_manifest_entry_for_file( $type_key, $filename, null );
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
	private static function create_page_from_seed( string $title, array $resolve ): ?WP_Post {
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
	 * Resolve an existing page post from a seed resolver (no creation).
	 *
	 * @param array{type:string, slug?:string, title:string} $resolve Seed resolver.
	 *
	 * @return WP_Post|null
	 */
	private static function resolve_page_post( array $resolve ): ?WP_Post {
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
	 * True when the on-disk file is not byte-identical to the canonical backup form (line endings, BOM, outer trim)
	 * while still matching WordPress after normalized comparison.
	 *
	 * @param string $absolute Absolute path to the plugin HTML file.
	 * @param string $post_content Post content as from {@see WP_Post::$post_content}.
	 *
	 * @return bool
	 */
	private static function plugin_file_needs_whitespace_normalization( string $absolute, string $post_content ): bool {
		if ( ! is_readable( $absolute ) ) {
			return false;
		}

		$raw = (string) file_get_contents( $absolute );
		$canonical = ContentSync::normalize_markup_for_storage( wp_unslash( $post_content ) );

		return $raw !== $canonical;
	}

	/**
	 * Rewrite on-disk plugin HTML to canonical backup form when raw bytes differ (BOM, CRLF, outer trim).
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename HTML basename.
	 * @param string $markup_source Markup to persist; {@see normalize_markup_for_storage()} is applied before write.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool True when the file was rewritten.
	 */
	private static function maybe_normalize_plugin_file_on_disk(
		string $type_key,
		string $filename,
		string $markup_source,
		array &$messages
	): bool {
		$absolute = self::resolve_content_file_path( $type_key, $filename );
		if ( ! is_readable( $absolute ) ) {
			return false;
		}

		$raw_disk = (string) file_get_contents( $absolute );
		$canonical = self::normalize_markup_for_storage( wp_unslash( $markup_source ) );
		if ( $raw_disk === $canonical ) {
			return false;
		}

		$relative_path = self::relative_content_type_file_path( $type_key, $filename );
		if ( ! self::write_content_markup( $type_key, $filename, $markup_source ) ) {
			$messages[] = sprintf(
				/* translators: %s: relative path */
				__( 'Failed to normalize %s. Check file permissions.', \ASC_AI_BOILER_TEXT_DOMAIN ),
				$relative_path
			);

			return false;
		}

		$messages[] = sprintf(
			/* translators: %s: relative plugin path */
			__( 'Normalized plugin file %s to canonical backup form.', \ASC_AI_BOILER_TEXT_DOMAIN ),
			$relative_path
		);

		return true;
	}

	/**
	 * Normalize markup for stable comparison (BOM strip, line endings, outer trim).
	 *
	 * @param string $markup Raw markup.
	 *
	 * @return string
	 */
	private static function normalize_markup_for_sync_compare( string $markup ): string {
		return ContentSync::normalize_markup_for_storage( $markup );
	}

	/**
	 * Whether file and database bodies match (ignores UTF-8 BOM, CRLF vs LF, outer trim).
	 *
	 * @param string $file_markup File markup.
	 * @param string $db_markup Post content.
	 *
	 * @return bool
	 */
	private static function markup_is_in_sync( string $file_markup, string $db_markup ): bool {
		return self::normalize_markup_for_sync_compare( $file_markup ) === self::normalize_markup_for_sync_compare( wp_unslash( $db_markup ) );
	}
}
