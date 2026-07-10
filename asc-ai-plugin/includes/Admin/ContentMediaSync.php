<?php
/**
 * Plugin-managed media under content/media/ synced with the WordPress media library.
 *
 * Files on disk are canonical for deploy; import loads them as attachments and applies
 * manifest bindings (settings keys, featured images). Export writes bound attachments back
 * to content/media/ and refreshes manifest media rows.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

use ASC\AI_BOILER\Core\Core;
use ASC\AI_BOILER\Core\Media;

/**
 * Sync between content/media/ and WordPress attachments.
 */
final class ContentMediaSync {

	/**
	 * Relative directory under the plugin root (trailing slash).
	 */
	public const MEDIA_RELATIVE_DIR = 'content/media/';

	/**
	 * Relative directory for static assets served directly (SVGs, fonts, icons) — never imported into the WP media library.
	 */
	public const MEDIA_OTHER_RELATIVE_DIR = 'content/other-media/';

	/**
	 * Filter: override the absolute path to content/other-media/ (trailing slash).
	 *
	 * @var string
	 */
	public const FILTER_OTHER_MEDIA_DIR = 'asc_ai_boiler_other_media_dir';

	/**
	 * Filter: override the public base URL of content/other-media/ (trailing slash).
	 *
	 * @var string
	 */
	public const FILTER_OTHER_MEDIA_URL = 'asc_ai_boiler_other_media_url';

	/**
	 * Attachment meta: plugin-relative path under {@see MEDIA_RELATIVE_DIR} (e.g. stock/blog-default.jpg).
	 */
	public const META_MEDIA_PATH = '_asc_ai_boiler_media_path';

	/**
	 * Filter: manifest media binding rows used on import and export.
	 *
	 * Each row: array{
	 *   media_filename: string,
	 *   target: 'setting'|'featured',
	 *   option?: string,
	 *   key?: string,
	 *   post_type?: string,
	 *   slug?: string
	 * }
	 *
	 * @var string
	 */
	public const FILTER_MEDIA_BINDINGS = 'asc_ai_boiler_media_bindings';

	/**
	 * Filter: plugin media path for a settings image key when no attachment is configured yet.
	 *
	 * @var string
	 */
	public const FILTER_SETTING_MEDIA_PATH = 'asc_ai_boiler_setting_media_path';

	/**
	 * Filter: plugin media path for a post (post type + slug) when no featured image is set.
	 *
	 * @var string
	 */
	public const FILTER_POST_MEDIA_PATH = 'asc_ai_boiler_post_media_path';

	/**
	 * Filter: override the absolute path to content/media/ (trailing slash).
	 *
	 * @var string
	 */
	public const FILTER_MEDIA_DIR = 'asc_ai_boiler_media_dir';

	/**
	 * Filter: override the public base URL of content/media/ (trailing slash).
	 *
	 * @var string
	 */
	public const FILTER_MEDIA_URL = 'asc_ai_boiler_media_url';

	/**
	 * @return string Absolute path to content/media/ (trailing slash). Filtered by {@see FILTER_MEDIA_DIR}.
	 */
	public static function get_media_directory(): string {
		$default = Core::get_instance()->get_plugin_path() . self::MEDIA_RELATIVE_DIR;
		return trailingslashit( (string) apply_filters( self::FILTER_MEDIA_DIR, $default ) );
	}

	/**
	 * Resolve a relative media path to a public URL, preferring the WordPress media library.
	 *
	 * Looks up the attachment stored with META_MEDIA_PATH matching $relative_path. If found,
	 * returns the attachment URL (enabling WebP and CDN delivery). Falls back to the direct
	 * plugin file URL so pages render even before an import has been run.
	 *
	 * @param string $relative_path Path under content/media/ (e.g. hero.jpg).
	 *
	 * @return string Public URL.
	 */
	public static function get_attachment_url_for_path( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}

		$attachment_id = self::find_attachment_id_by_media_path( $relative_path );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $url ) && '' !== $url ) {
				return esc_url( $url );
			}
		}

		return self::get_media_url( $relative_path );
	}

	/**
	 * @param string $relative_path Path under content/media/ (e.g. hero.jpg or blog-default.jpg).
	 *
	 * @return string Public URL (direct plugin file, no media library lookup).
	 */
	public static function get_media_url( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}

		$default = Core::get_instance()->get_plugin_url() . self::MEDIA_RELATIVE_DIR;
		$base = trailingslashit( (string) apply_filters( self::FILTER_MEDIA_URL, $default ) );

		return esc_url( $base . $relative_path );
	}

	/**
	 * @return void
	 */
	public static function ensure_media_directory_exists(): void {
		$root = self::get_media_directory();
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}
		self::ensure_other_media_directory_exists();
	}

	/**
	 * Absolute path to content/other-media/ (trailing slash). Filtered by {@see FILTER_OTHER_MEDIA_DIR}.
	 *
	 * @return string
	 */
	public static function get_other_media_directory(): string {
		$default = Core::get_instance()->get_plugin_path() . self::MEDIA_OTHER_RELATIVE_DIR;
		return trailingslashit( (string) apply_filters( self::FILTER_OTHER_MEDIA_DIR, $default ) );
	}

	/**
	 * Public URL for a file under content/other-media/. Files are served directly — not imported into WordPress.
	 *
	 * @param string $relative_path Path relative to content/other-media/ (e.g. `moon.svg`).
	 *
	 * @return string Escaped public URL.
	 */
	public static function get_other_media_url( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return '';
		}
		$default = Core::get_instance()->get_plugin_url() . self::MEDIA_OTHER_RELATIVE_DIR;
		$base = trailingslashit( (string) apply_filters( self::FILTER_OTHER_MEDIA_URL, $default ) );
		return esc_url( $base . $relative_path );
	}

	/**
	 * @return void
	 */
	public static function ensure_other_media_directory_exists(): void {
		$root = self::get_other_media_directory();
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}
	}

	/**
	 * @param string $relative_path Path under content/media/.
	 *
	 * @return string Absolute file path.
	 */
	public static function resolve_media_file_path( string $relative_path ): string {
		$relative_path = self::normalize_relative_path( $relative_path );
		$root = wp_normalize_path( self::get_media_directory() );
		$target = wp_normalize_path( $root . $relative_path );
		if ( 0 !== strpos( $target, $root ) ) {
			return '';
		}

		return $target;
	}

	/**
	 * @param string $relative_path Path under content/media/.
	 *
	 * @return bool
	 */
	public static function is_valid_media_relative_path( string $relative_path ): bool {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return false;
		}

		if ( str_contains( $relative_path, '..' ) ) {
			return false;
		}

		return 1 === preg_match(
			'#^[a-zA-Z0-9][a-zA-Z0-9_\-./]*\\.(jpg|jpeg|png|webp|gif|svg)$#',
			$relative_path
		);
	}

	/**
	 * List media files under content/media/ (relative paths, sorted).
	 *
	 * @return list<string>
	 */
	public static function list_media_files(): array {
		$root = self::get_media_directory();
		if ( ! is_dir( $root ) ) {
			return array();
		}

		$out = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
		);

		$root_norm = wp_normalize_path( $root );
		foreach ( $iterator as $file_info ) {
			if ( ! $file_info instanceof \SplFileInfo || ! $file_info->isFile() ) {
				continue;
			}

			$absolute = wp_normalize_path( $file_info->getPathname() );
			if ( 0 !== strpos( $absolute, $root_norm ) ) {
				continue;
			}

			$relative = ltrim( substr( $absolute, strlen( $root_norm ) ), '/' );
			if ( self::is_valid_media_relative_path( $relative ) ) {
				$out[] = $relative;
			}
		}

		sort( $out, SORT_STRING );

		return $out;
	}

	/**
	 * @return string Absolute path to content-manifest.json.
	 */
	public static function get_content_manifest_path(): string {
		return dirname( rtrim( self::get_media_directory(), '/' ) ) . '/content-manifest.json';
	}

	/**
	 * Media rows from content-manifest.json (`media` top-level key).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function load_manifest_media_rows(): array {
		$path = self::get_content_manifest_path();
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$json = file_get_contents( $path );
		if ( false === $json || '' === $json ) {
			return array();
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['media'] ) || ! is_array( $data['media'] ) ) {
			return array();
		}

		$rows = array();
		foreach ( $data['media'] as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function load_manifest_media_bindings(): array {
		$path = self::get_content_manifest_path();
		if ( ! is_readable( $path ) ) {
			return self::filtered_media_bindings();
		}

		$json = file_get_contents( $path );
		if ( false === $json || '' === $json ) {
			return self::filtered_media_bindings();
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['media_bindings'] ) || ! is_array( $data['media_bindings'] ) ) {
			return self::filtered_media_bindings();
		}

		$rows = array();
		foreach ( $data['media_bindings'] as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		if ( array() === $rows ) {
			return self::filtered_media_bindings();
		}

		return $rows;
	}

	/**
	 * Import plugin media files into the WordPress media library and apply bindings.
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return array{updated:int, processed:int}
	 */
	public static function import_from_plugin_files( array &$messages ): array {
		self::ensure_media_directory_exists();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$manifest_rows = self::index_manifest_media_rows( self::load_manifest_media_rows() );
		$updated = 0;
		$processed = 0;

		foreach ( self::list_media_files() as $relative_path ) {
			$processed++;
			$row = $manifest_rows[ $relative_path ] ?? array();
			if ( self::import_media_file( $relative_path, $row, $messages ) ) {
				$updated++;
			}
		}

		$bindings_updated = self::apply_media_bindings( $messages );
		$updated += $bindings_updated;

		return array(
			'updated' => $updated,
			'processed' => $processed,
		);
	}

	/**
	 * Export bound attachments to content/media/ and build manifest media rows.
	 *
	 * @param list<string> $messages Log lines.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function export_to_plugin_files( array &$messages ): array {
		self::ensure_media_directory_exists();

		$rows_by_path = array();
		$exported = 0;

		foreach ( self::collect_attachment_ids_for_export() as $attachment_id ) {
			$relative_path = trim( (string) get_post_meta( $attachment_id, self::META_MEDIA_PATH, true ) );
			if ( '' === $relative_path ) {
				continue;
			}

			if ( self::export_attachment_to_media_path( $attachment_id, $relative_path, $messages ) ) {
				$exported++;
			}

			$rows_by_path[ $relative_path ] = self::build_manifest_media_row_from_attachment( $attachment_id, $relative_path );
		}

		if ( $exported > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of media files */
				_n(
					'Exported %d media file to content/media/.',
					'Exported %d media files to content/media/.',
					$exported,
					\ASC_AI_PLUGIN_DOMAIN
				),
				$exported
			);
		}

		ksort( $rows_by_path, SORT_STRING );

		return array_values( $rows_by_path );
	}

	/**
	 * Build manifest `media` rows from on-disk files (after export).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function build_manifest_media_rows_for_manifest(): array {
		$existing = self::index_manifest_media_rows( self::load_manifest_media_rows() );
		$rows = array();

		foreach ( self::list_media_files() as $relative_path ) {
			$attachment_id = self::find_attachment_id_by_media_path( $relative_path );
			if ( $attachment_id > 0 ) {
				$rows[] = self::build_manifest_media_row_from_attachment( $attachment_id, $relative_path );
			} else {
				$row = $existing[ $relative_path ] ?? array();
				if ( ! isset( $row['filename'] ) ) {
					$row['filename'] = $relative_path;
				}

				$absolute = self::resolve_media_file_path( $relative_path );
				if ( '' === $absolute || ! is_readable( $absolute ) ) {
					continue;
				}

				$filetype = wp_check_filetype( basename( $absolute ), null );
				$mime = is_array( $filetype ) && isset( $filetype['type'] ) ? (string) $filetype['type'] : '';
				if ( '' !== $mime ) {
					$row['mime'] = $mime;
				}

				if ( ! isset( $row['title'] ) || '' === trim( (string) $row['title'] ) ) {
					$row['title'] = self::manifest_title_for_row( array(), $relative_path );
				}

				if ( ! isset( $row['alt'] ) ) {
					$row['alt'] = '';
				}

				if ( ! isset( $row['caption'] ) ) {
					$row['caption'] = '';
				}

				if ( ! isset( $row['description'] ) ) {
					$row['description'] = '';
				}

				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function build_manifest_media_bindings_for_export(): array {
		$bindings = array();

		// Preserve setting bindings from existing bindings/filters
		$existing_bindings = self::load_manifest_media_bindings();
		$settings_keys = array();

		foreach ( $existing_bindings as $binding ) {
			if ( ! is_array( $binding ) ) {
				continue;
			}

			$target = isset( $binding['target'] ) ? trim( (string) $binding['target'] ) : '';
			if ( 'setting' === $target ) {
				$option = isset( $binding['option'] ) ? (string) $binding['option'] : '';
				$key = isset( $binding['key'] ) ? (string) $binding['key'] : '';
				if ( '' === $option || '' === $key ) {
					continue;
				}

				$settings_key = $option . '::' . $key;
				if ( isset( $settings_keys[ $settings_key ] ) ) {
					continue;
				}
				$settings_keys[ $settings_key ] = true;

				$settings = get_option( $option, array() );
				if ( is_array( $settings ) && isset( $settings[ $key ] ) ) {
					$attachment_id = (int) $settings[ $key ];
					if ( $attachment_id > 0 ) {
						$media_filename = self::ensure_attachment_has_media_path( $attachment_id );
						if ( '' !== $media_filename ) {
							$bindings[] = array(
								'media_filename' => $media_filename,
								'target'         => 'setting',
								'option'         => $option,
								'key'            => $key,
							);
							continue;
						}
					}
				}

				$bindings[] = $binding;
			}
		}

		// Dynamically collect featured image bindings for all synced post types
		if ( class_exists( '\ASC\AI_BOILER\Admin\ContentSyncProfile' ) ) {
			$sync_types = \ASC\AI_BOILER\Admin\ContentSyncProfile::sync_types();
			foreach ( $sync_types as $type_key => $type_config ) {
				$post_type = isset( $type_config['post_type'] ) ? (string) $type_config['post_type'] : '';
				if ( '' === $post_type ) {
					continue;
				}

				$query = new \WP_Query(
					array(
						'post_type'           => $post_type,
						'post_status'         => 'publish',
						'posts_per_page'      => -1,
						'no_found_rows'       => true,
						'ignore_sticky_posts' => true,
					)
				);

				foreach ( $query->posts as $post ) {
					if ( ! $post instanceof \WP_Post ) {
						continue;
					}

					$thumbnail_id = (int) get_post_thumbnail_id( $post->ID );
					if ( $thumbnail_id > 0 ) {
						$media_filename = self::ensure_attachment_has_media_path( $thumbnail_id );
						if ( '' !== $media_filename ) {
							$bindings[] = array(
								'media_filename' => $media_filename,
								'target'         => 'featured',
								'post_type'      => $post->post_type,
								'slug'           => $post->post_name,
							);
						}
					}
				}
				wp_reset_postdata();
			}
		}

		// Canonically sort all bindings
		usort(
			$bindings,
			function( $a, $b ) {
				$file_a = $a['media_filename'] ?? '';
				$file_b = $b['media_filename'] ?? '';
				if ( $file_a === $file_b ) {
					$target_a = $a['target'] ?? '';
					$target_b = $b['target'] ?? '';
					if ( $target_a === $target_b ) {
						$slug_a = $a['slug'] ?? $a['key'] ?? '';
						$slug_b = $b['slug'] ?? $b['key'] ?? '';
						return strcmp( $slug_a, $slug_b );
					}
					return strcmp( $target_a, $target_b );
				}
				return strcmp( $file_a, $file_b );
			}
		);

		return $bindings;
	}

	/**
	 * @param string $relative_path Path under content/media/.
	 * @param array<string, mixed> $manifest_row Optional manifest metadata.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool True when created or updated.
	 */
	public static function import_media_file( string $relative_path, array $manifest_row, array &$messages ): bool {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( ! self::is_valid_media_relative_path( $relative_path ) ) {
			return false;
		}

		$absolute = self::resolve_media_file_path( $relative_path );
		if ( '' === $absolute || ! is_readable( $absolute ) ) {
			return false;
		}

		$disk_hash = md5_file( $absolute );
		if ( ! is_string( $disk_hash ) ) {
			return false;
		}

		$attachment_id = self::find_attachment_id_by_media_path( $relative_path );
		$changed = false;

		if ( $attachment_id <= 0 ) {
			$attachment_id = self::create_attachment_from_disk( $relative_path, $manifest_row, $messages );
			if ( $attachment_id <= 0 ) {
				return false;
			}

			$changed = true;
		} else {
			$attached = get_attached_file( $attachment_id );
			if ( is_string( $attached ) && is_readable( $attached ) ) {
				$existing_hash = md5_file( $attached );
				if ( is_string( $existing_hash ) && $existing_hash !== $disk_hash ) {
					if ( self::replace_attachment_file( $attachment_id, $absolute, $messages ) ) {
						$changed = true;
					}
				}
			} else {
				if ( self::replace_attachment_file( $attachment_id, $absolute, $messages ) ) {
					$changed = true;
				}
			}

			if ( self::apply_manifest_metadata_to_attachment( $attachment_id, $manifest_row ) ) {
				$changed = true;
			}
		}

		if ( $changed ) {
			$messages[] = sprintf(
				/* translators: %s: relative media path */
				__( 'Imported media %s.', \ASC_AI_PLUGIN_DOMAIN ),
				self::MEDIA_RELATIVE_DIR . $relative_path
			);
		}

		return $changed;
	}

	/**
	 * @param list<string> $messages Log lines.
	 *
	 * @return int Number of bindings applied.
	 */
	public static function apply_media_bindings( array &$messages ): int {
		$applied = 0;

		foreach ( self::load_manifest_media_bindings() as $binding ) {
			if ( ! is_array( $binding ) ) {
				continue;
			}

			$media_filename = isset( $binding['media_filename'] ) ? self::normalize_relative_path( (string) $binding['media_filename'] ) : '';
			if ( ! self::is_valid_media_relative_path( $media_filename ) ) {
				continue;
			}

			$attachment_id = self::find_attachment_id_by_media_path( $media_filename );
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$target = isset( $binding['target'] ) ? trim( (string) $binding['target'] ) : '';
			if ( 'setting' === $target ) {
				if ( self::apply_setting_binding( $binding, $attachment_id, $messages ) ) {
					$applied++;
				}
				continue;
			}

			if ( 'featured' === $target ) {
				if ( self::apply_featured_binding( $binding, $attachment_id, $messages ) ) {
					$applied++;
				}
			}
		}

		return $applied;
	}

	/**
	 * @param string $relative_path Path under content/media/.
	 *
	 * @return int Attachment ID or 0.
	 */
	/**
	 * Compare content/media/ files against WordPress attachments and return rows in the same
	 * shape as {@see \ASC\AI_BOILER\Admin\ContentSync::run_detect_content_differences()} differences.
	 *
	 * @return list<array{relative_path:string, issues:list<string>, suggestion:string, suggestion_note:string, file_modified_gmt:string, wp_modified_gmt:string}>
	 */
	public static function detect_differences(): array {
		$differences = array();
		$manifest_rows = self::index_manifest_media_rows( self::load_manifest_media_rows() );
		$disk_files = self::list_media_files();
		$disk_files_indexed = array_fill_keys( $disk_files, true );

		// 1. Check disk files vs WordPress database
		foreach ( $disk_files as $relative_path ) {
			$absolute      = self::resolve_media_file_path( $relative_path );
			$display_path  = self::MEDIA_RELATIVE_DIR . $relative_path;
			$file_mtime    = ( '' !== $absolute && is_file( $absolute ) ) ? filemtime( $absolute ) : false;
			$file_iso      = false !== $file_mtime ? gmdate( 'c', (int) $file_mtime ) : '';
			$attachment_id = self::find_attachment_id_by_media_path( $relative_path );

			if ( $attachment_id <= 0 ) {
				$differences[] = array(
					'relative_path'    => $display_path,
					'issues'           => array( __( 'Media file on disk has no matching attachment in the WordPress media library.', \ASC_AI_PLUGIN_DOMAIN ) ),
					'suggestion'       => 'import',
					'suggestion_note'  => __( 'Suggested: Import from plugin files to add this media file to WordPress.', \ASC_AI_PLUGIN_DOMAIN ),
					'file_modified_gmt' => $file_iso,
					'wp_modified_gmt'  => '',
				);
				continue;
			}

			$disk_hash    = ( '' !== $absolute && is_readable( $absolute ) ) ? md5_file( $absolute ) : false;
			$attached     = get_attached_file( $attachment_id );
			$attach_hash  = ( is_string( $attached ) && is_readable( $attached ) ) ? md5_file( $attached ) : false;

			$file_mismatch = false !== $disk_hash && false !== $attach_hash && $disk_hash !== $attach_hash;
			$wp_post       = get_post( $attachment_id );
			$wp_ts         = $wp_post ? (int) get_post_modified_time( 'U', true, $wp_post ) : 0;
			$wp_iso        = $wp_ts > 0 ? gmdate( 'c', $wp_ts ) : '';

			if ( $file_mismatch ) {
				$differences[] = array(
					'relative_path'    => $display_path,
					'issues'           => array( __( 'Media file on disk differs from the WordPress attachment (MD5 mismatch).', \ASC_AI_PLUGIN_DOMAIN ) ),
					'suggestion'       => 'import',
					'suggestion_note'  => __( 'Suggested: Import from plugin files to update this media attachment in WordPress.', \ASC_AI_PLUGIN_DOMAIN ),
					'file_modified_gmt' => $file_iso,
					'wp_modified_gmt'  => $wp_iso,
				);
			} else {
				// Check metadata differences
				$wp_row = self::build_manifest_media_row_from_attachment( $attachment_id, $relative_path );
				$row = $manifest_rows[ $relative_path ] ?? array();

				$meta_differ = false;
				foreach ( array( 'title', 'alt', 'caption', 'description' ) as $key ) {
					$wp_val = isset( $wp_row[ $key ] ) ? trim( (string) $wp_row[ $key ] ) : '';
					$manifest_val = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
					if ( $wp_val !== $manifest_val ) {
						$meta_differ = true;
						break;
					}
				}

				if ( $meta_differ ) {
					$differences[] = array(
						'relative_path'    => $display_path,
						'issues'           => array( __( 'Manifest metadata (title, alt, caption, or description) differs from the WordPress attachment.', \ASC_AI_PLUGIN_DOMAIN ) ),
						'suggestion'       => 'export',
						'suggestion_note'  => __( 'Suggested: Export to plugin files to update the manifest with WordPress metadata.', \ASC_AI_PLUGIN_DOMAIN ),
						'file_modified_gmt' => $file_iso,
						'wp_modified_gmt'  => $wp_iso,
					);
				}
			}
		}

		// 2. Check for WordPress database attachments missing from disk
		$db_attachment_ids = self::collect_attachment_ids_for_export();
		foreach ( $db_attachment_ids as $attachment_id ) {
			$relative_path = trim( (string) get_post_meta( $attachment_id, self::META_MEDIA_PATH, true ) );
			if ( '' === $relative_path ) {
				continue;
			}

			if ( ! isset( $disk_files_indexed[ $relative_path ] ) ) {
				$display_path  = self::MEDIA_RELATIVE_DIR . $relative_path;
				$wp_post       = get_post( $attachment_id );
				$wp_ts         = $wp_post ? (int) get_post_modified_time( 'U', true, $wp_post ) : 0;
				$wp_iso        = $wp_ts > 0 ? gmdate( 'c', $wp_ts ) : '';

				$differences[] = array(
					'relative_path'    => $display_path,
					'issues'           => array( __( 'Media attachment in WordPress has no matching file on disk.', \ASC_AI_PLUGIN_DOMAIN ) ),
					'suggestion'       => 'export',
					'suggestion_note'  => __( 'Suggested: Export to plugin files to write the missing media file to disk.', \ASC_AI_PLUGIN_DOMAIN ),
					'file_modified_gmt' => '',
					'wp_modified_gmt'  => $wp_iso,
				);
			}
		}

		// 3. Check bindings differences
		$db_bindings = self::build_manifest_media_bindings_for_export();
		$manifest_bindings = self::load_manifest_media_bindings();
		if ( wp_json_encode( $db_bindings ) !== wp_json_encode( $manifest_bindings ) ) {
			$differences[] = array(
				'relative_path'     => 'content-manifest.json',
				'issues'            => array( __( 'Media bindings in manifest differ from the active settings and featured images in WordPress.', \ASC_AI_PLUGIN_DOMAIN ) ),
				'suggestion'        => 'export',
				'suggestion_note'   => __( 'Suggested: Export to plugin files to update the manifest media bindings.', \ASC_AI_PLUGIN_DOMAIN ),
				'file_modified_gmt' => '',
				'wp_modified_gmt'   => '',
			);
		}

		return $differences;
	}

	public static function find_attachment_id_by_media_path( string $relative_path ): int {
		$relative_path = self::normalize_relative_path( $relative_path );
		if ( '' === $relative_path ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'meta_key' => self::META_MEDIA_PATH,
				'meta_value' => $relative_path,
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			return 0;
		}

		$attachment_id = (int) $query->posts[0];
		wp_reset_postdata();

		if ( $attachment_id <= 0 ) {
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * @param string $setting_key Settings field key (product-defined).
	 *
	 * @return string Plugin media URL or empty string.
	 */
	public static function get_setting_media_url( string $setting_key ): string {
		$relative_path = apply_filters( self::FILTER_SETTING_MEDIA_PATH, '', $setting_key );
		if ( ! is_string( $relative_path ) || '' === $relative_path ) {
			return '';
		}

		return self::get_attachment_url_for_path( $relative_path );
	}

	/**
	 * @param string $post_type Post type slug.
	 * @param string $slug Post slug.
	 *
	 * @return string Plugin media URL or empty string.
	 */
	public static function get_post_media_url( string $post_type, string $slug ): string {
		$relative_path = apply_filters( self::FILTER_POST_MEDIA_PATH, '', $post_type, $slug );
		if ( ! is_string( $relative_path ) || '' === $relative_path ) {
			return '';
		}

		return self::get_attachment_url_for_path( $relative_path );
	}

	/**
	 * @param list<array<string, mixed>> $rows Manifest media rows.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function index_manifest_media_rows( array $rows ): array {
		$indexed = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['filename'] ) ) {
				continue;
			}

			$filename = self::normalize_relative_path( (string) $row['filename'] );
			if ( self::is_valid_media_relative_path( $filename ) ) {
				$indexed[ $filename ] = $row;
			}
		}

		return $indexed;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function filtered_media_bindings(): array {
		$bindings = apply_filters( self::FILTER_MEDIA_BINDINGS, array() );
		if ( ! is_array( $bindings ) ) {
			return array();
		}

		$out = array();
		foreach ( $bindings as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * @return list<int>
	 */
	private static function collect_attachment_ids_for_export(): array {
		$ids = array();

		// 1. Collect from updated bindings (possibly new ones from WP settings/posts)
		$bindings = self::build_manifest_media_bindings_for_export();
		foreach ( $bindings as $binding ) {
			if ( ! is_array( $binding ) ) {
				continue;
			}

			$target = isset( $binding['target'] ) ? trim( (string) $binding['target'] ) : '';
			if ( 'setting' === $target ) {
				$option = isset( $binding['option'] ) ? (string) $binding['option'] : '';
				$key = isset( $binding['key'] ) ? (string) $binding['key'] : '';
				if ( '' === $option || '' === $key ) {
					continue;
				}

				$settings = get_option( $option, array() );
				if ( is_array( $settings ) && isset( $settings[ $key ] ) ) {
					$attachment_id = (int) $settings[ $key ];
					if ( $attachment_id > 0 ) {
						$ids[ $attachment_id ] = $attachment_id;
					}
				}
				continue;
			}

			if ( 'featured' === $target ) {
				$post_type = isset( $binding['post_type'] ) ? trim( (string) $binding['post_type'] ) : '';
				$slug = isset( $binding['slug'] ) ? trim( (string) $binding['slug'] ) : '';
				if ( '' === $post_type || '' === $slug ) {
					continue;
				}

				$post = get_page_by_path( $slug, OBJECT, $post_type );
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$thumbnail_id = (int) get_post_thumbnail_id( (int) $post->ID );
				if ( $thumbnail_id > 0 ) {
					$ids[ $thumbnail_id ] = $thumbnail_id;
				}
			}
		}

		// 2. Collect all other plugin-managed attachments from the database
		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'meta_key'               => self::META_MEDIA_PATH,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $query->posts as $id ) {
			$attachment_id = (int) $id;
			if ( $attachment_id > 0 ) {
				$ids[ $attachment_id ] = $attachment_id;
			}
		}
		wp_reset_postdata();

		return array_values( $ids );
	}

	/**
	 * @param string $relative_path Path under content/media/.
	 * @param array<string, mixed> $manifest_row Manifest metadata.
	 * @param list<string> $messages Log lines.
	 *
	 * @return int Attachment ID or 0.
	 */
	private static function create_attachment_from_disk( string $relative_path, array $manifest_row, array &$messages ): int {
		$absolute = self::resolve_media_file_path( $relative_path );
		if ( '' === $absolute || ! is_readable( $absolute ) ) {
			return 0;
		}

		$file_contents = file_get_contents( $absolute );
		if ( false === $file_contents ) {
			return 0;
		}

		$upload = wp_upload_bits( basename( $absolute ), null, $file_contents );
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) ) {
			$messages[] = sprintf(
				/* translators: %s: relative media path */
				__( 'Failed to import media %s.', \ASC_AI_PLUGIN_DOMAIN ),
				self::MEDIA_RELATIVE_DIR . $relative_path
			);
			return 0;
		}

		$filetype = wp_check_filetype( basename( $absolute ), null );
		$mime = is_array( $filetype ) && isset( $filetype['type'] ) ? (string) $filetype['type'] : '';
		if ( '' === $mime ) {
			$messages[] = sprintf(
				/* translators: %s: relative media path */
				__( 'Skipped media %s: unknown MIME type.', \ASC_AI_PLUGIN_DOMAIN ),
				self::MEDIA_RELATIVE_DIR . $relative_path
			);
			return 0;
		}

		$title = self::manifest_title_for_row( $manifest_row, $relative_path );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title' => $title,
				'post_status' => 'inherit',
			),
			(string) $upload['file']
		);

		if ( is_wp_error( $attachment_id ) || ! is_int( $attachment_id ) || $attachment_id <= 0 ) {
			$messages[] = sprintf(
				/* translators: %s: relative media path */
				__( 'Failed to import media %s.', \ASC_AI_PLUGIN_DOMAIN ),
				self::MEDIA_RELATIVE_DIR . $relative_path
			);
			return 0;
		}

		update_post_meta( $attachment_id, self::META_MEDIA_PATH, $relative_path );
		self::apply_manifest_metadata_to_attachment( $attachment_id, $manifest_row );

		$metadata = wp_generate_attachment_metadata( $attachment_id, (string) $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return $attachment_id;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @param string $absolute_source Absolute path to source bytes.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool
	 */
	private static function replace_attachment_file( int $attachment_id, string $absolute_source, array &$messages ): bool {
		$destination = get_attached_file( $attachment_id );
		if ( ! is_string( $destination ) || '' === $destination ) {
			$upload = wp_upload_dir();
			if ( ! is_array( $upload ) || ! empty( $upload['error'] ) ) {
				return false;
			}

			$destination = trailingslashit( (string) $upload['path'] ) . basename( $absolute_source );
		}

		if ( ! copy( $absolute_source, $destination ) ) {
			$messages[] = sprintf(
				/* translators: %s: attachment title */
				__( 'Failed to replace attachment file for %s.', \ASC_AI_PLUGIN_DOMAIN ),
				get_the_title( $attachment_id )
			);
			return false;
		}

		update_attached_file( $attachment_id, $destination );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $destination );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return true;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @param string $relative_path Path under content/media/.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool
	 */
	private static function export_attachment_to_media_path( int $attachment_id, string $relative_path, array &$messages ): bool {
		$relative_path = self::normalize_relative_path( $relative_path );
		$absolute = self::resolve_media_file_path( $relative_path );
		if ( '' === $absolute ) {
			return false;
		}

		$source = get_attached_file( $attachment_id );
		if ( ! is_string( $source ) || ! is_readable( $source ) ) {
			return false;
		}

		$target_dir = dirname( $absolute );
		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$source_hash = md5_file( $source );
		$target_hash = is_file( $absolute ) ? md5_file( $absolute ) : false;
		if ( is_string( $source_hash ) && is_string( $target_hash ) && $source_hash === $target_hash ) {
			update_post_meta( $attachment_id, self::META_MEDIA_PATH, $relative_path );
			return false;
		}

		if ( ! copy( $source, $absolute ) ) {
			$messages[] = sprintf(
				/* translators: %s: relative media path */
				__( 'Failed to write media %s.', \ASC_AI_PLUGIN_DOMAIN ),
				self::MEDIA_RELATIVE_DIR . $relative_path
			);
			return false;
		}

		update_post_meta( $attachment_id, self::META_MEDIA_PATH, $relative_path );

		return true;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @param string $relative_path Path under content/media/.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_manifest_media_row_from_attachment( int $attachment_id, string $relative_path ): array {
		$filetype = wp_check_filetype( basename( $relative_path ), null );
		$mime = is_array( $filetype ) && isset( $filetype['type'] ) ? (string) $filetype['type'] : '';

		$post = get_post( $attachment_id );
		$title = $post ? (string) $post->post_title : '';
		$caption = $post ? (string) $post->post_excerpt : '';
		$description = $post ? (string) $post->post_content : '';
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		$row = array(
			'filename'    => $relative_path,
			'title'       => $title,
			'alt'         => $alt,
			'caption'     => $caption,
			'description' => $description,
		);

		if ( '' !== $mime ) {
			$row['mime'] = $mime;
		}

		return $row;
	}

	/**
	 * Ensure that an attachment has a META_MEDIA_PATH meta value.
	 *
	 * If none exists, defaults to the basename of the attached file.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string Media relative path.
	 */
	private static function ensure_attachment_has_media_path( int $attachment_id ): string {
		$relative_path = trim( (string) get_post_meta( $attachment_id, self::META_MEDIA_PATH, true ) );
		if ( '' !== $relative_path ) {
			return $relative_path;
		}

		$file = get_attached_file( $attachment_id );
		if ( is_string( $file ) && '' !== $file ) {
			$basename = basename( $file );
			if ( '' !== $basename ) {
				update_post_meta( $attachment_id, self::META_MEDIA_PATH, $basename );
				return $basename;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $binding Binding row.
	 * @param int $attachment_id Attachment ID.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool
	 */
	private static function apply_setting_binding( array $binding, int $attachment_id, array &$messages ): bool {
		$option = isset( $binding['option'] ) ? (string) $binding['option'] : '';
		$key = isset( $binding['key'] ) ? (string) $binding['key'] : '';
		if ( '' === $option || '' === $key ) {
			return false;
		}

		$settings = get_option( $option, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$current = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
		if ( $current === $attachment_id ) {
			return false;
		}

		$settings[ $key ] = $attachment_id;
		update_option( $option, $settings );

		$messages[] = sprintf(
			/* translators: 1: option key, 2: media path */
			__( 'Linked media binding %1$s → %2$s.', \ASC_AI_PLUGIN_DOMAIN ),
			$key,
			(string) ( $binding['media_filename'] ?? '' )
		);

		return true;
	}

	/**
	 * @param array<string, mixed> $binding Binding row.
	 * @param int $attachment_id Attachment ID.
	 * @param list<string> $messages Log lines.
	 *
	 * @return bool
	 */
	private static function apply_featured_binding( array $binding, int $attachment_id, array &$messages ): bool {
		$post_type = isset( $binding['post_type'] ) ? trim( (string) $binding['post_type'] ) : '';
		$slug = isset( $binding['slug'] ) ? trim( (string) $binding['slug'] ) : '';
		if ( '' === $post_type || '' === $slug ) {
			return false;
		}

		$post = get_page_by_path( $slug, OBJECT, $post_type );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$current = (int) get_post_thumbnail_id( (int) $post->ID );
		if ( $current === $attachment_id ) {
			return false;
		}

		set_post_thumbnail( (int) $post->ID, $attachment_id );

		$messages[] = sprintf(
			/* translators: 1: post slug, 2: media path */
			__( 'Set featured image for %1$s from %2$s.', \ASC_AI_PLUGIN_DOMAIN ),
			$slug,
			(string) ( $binding['media_filename'] ?? '' )
		);

		return true;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @param array<string, mixed> $manifest_row Manifest metadata.
	 *
	 * @return bool True when metadata changed.
	 */
	private static function apply_manifest_metadata_to_attachment( int $attachment_id, array $manifest_row ): bool {
		$changed = false;
		$post = get_post( $attachment_id );
		if ( $post ) {
			$update_data = array();
			$title = self::manifest_title_for_row( $manifest_row, '' );
			if ( '' !== $title && $title !== $post->post_title ) {
				$update_data['post_title'] = $title;
			}

			if ( isset( $manifest_row['caption'] ) ) {
				$caption = trim( (string) $manifest_row['caption'] );
				if ( $caption !== trim( $post->post_excerpt ) ) {
					$update_data['post_excerpt'] = $caption;
				}
			}

			if ( isset( $manifest_row['description'] ) ) {
				$description = trim( (string) $manifest_row['description'] );
				if ( $description !== trim( $post->post_content ) ) {
					$update_data['post_content'] = $description;
				}
			}

			if ( ! empty( $update_data ) ) {
				$update_data['ID'] = $attachment_id;
				wp_update_post( $update_data );
				$changed = true;
			}
		}

		if ( isset( $manifest_row['alt'] ) ) {
			$alt = trim( (string) $manifest_row['alt'] );
			$current_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
			if ( $alt !== $current_alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * @param array<string, mixed> $manifest_row Manifest metadata.
	 * @param string $relative_path Fallback title source.
	 *
	 * @return string
	 */
	private static function manifest_title_for_row( array $manifest_row, string $relative_path ): string {
		if ( isset( $manifest_row['title'] ) ) {
			$title = trim( (string) $manifest_row['title'] );
			if ( '' !== $title ) {
				return $title;
			}
		}

		if ( '' === $relative_path ) {
			return '';
		}

		$base = pathinfo( $relative_path, PATHINFO_FILENAME );
		$base = str_replace( array( '-', '_' ), ' ', $base );

		return ucwords( $base );
	}

	/**
	 * @param string $relative_path Path under content/media/.
	 *
	 * @return string
	 */
	private static function normalize_relative_path( string $relative_path ): string {
		$relative_path = str_replace( '\\', '/', trim( $relative_path ) );
		return ltrim( $relative_path, '/' );
	}
}
