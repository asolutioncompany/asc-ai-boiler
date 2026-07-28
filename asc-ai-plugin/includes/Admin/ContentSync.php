<?php
/**
 * Static content sync (plugin HTML and content-manifest.json vs WordPress).
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Admin\ContentMediaSync;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * @since 1.0
 * Static HTML sync between plugin content files and WordPress posts.
 */
final class ContentSync {

	/**
	 * Summary totals of scanned pages, partials, posts & CPTs, and images across disk and WordPress.
	 *
	 * Note: The logic for bucketing types into pages/partials/posts is intentionally duplicated in
	 * `run_detect_content_differences()`. This function provides a lightweight way to fetch counts
	 * during batch processes without incurring the heavy cost of running a full differences detection pass.
	 * If you update the bucketing logic here, you MUST update it in `run_detect_content_differences()` as well.
	 *
	 * @return array{pages_scanned:int, partials_scanned:int, posts_scanned:int, images_scanned:int}
	 */
	public static function get_sync_summary_totals(): array {
		$pages_scanned_map = array();
		$partials_scanned_map = array();
		$posts_scanned_map = array();
		$images_scanned_map = array();

		foreach ( ContentExporter::collect_orphan_wordpress_posts() as $row ) {
			$relative_path = (string) $row['relative_path'];
			$type_key = (string) ( $row['type_key'] ?? '' );
			$post_id = (int) $row['post_id'];
			$post = get_post( $post_id );
			$post_type = ContentImporter::get_post_type_for_job( $type_key );
			if ( $post instanceof WP_Post ) {
				$post_type = (string) $post->post_type;
			}

			if ( 'asc_boiler_partial' === $post_type ) {
				$partials_scanned_map[ $relative_path ] = true;
			} elseif ( 'page' === $post_type ) {
				$pages_scanned_map[ $relative_path ] = true;
			} else {
				$posts_scanned_map[ $relative_path ] = true;
			}
		}

		foreach ( ContentImporter::collect_import_file_jobs() as $job ) {
			$type_key = (string) $job['type'];
			$filename = (string) $job['filename'];
			$relative_path = self::relative_content_type_file_path( $type_key, $filename );
			$post_type = ContentImporter::get_post_type_for_job( $type_key, $filename );

			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$partials_scanned_map[ $relative_path ] = true;
			} elseif ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
				$pages_scanned_map[ $relative_path ] = true;
			} else {
				$posts_scanned_map[ $relative_path ] = true;
			}
		}

		if ( SyncConfig::is_media_sync_enabled() ) {
			foreach ( ContentMediaSync::list_media_files() as $media_rel ) {
				$images_scanned_map[ ContentMediaSync::MEDIA_RELATIVE_DIR . $media_rel ] = true;
			}
			foreach ( ContentMediaSync::collect_attachment_ids_for_export() as $attachment_id ) {
				$media_rel = trim( (string) get_post_meta( $attachment_id, ContentMediaSync::META_MEDIA_PATH, true ) );
				if ( '' !== $media_rel ) {
					$images_scanned_map[ ContentMediaSync::MEDIA_RELATIVE_DIR . $media_rel ] = true;
				}
			}
		}

		return array(
			'pages_scanned' => count( $pages_scanned_map ),
			'partials_scanned' => count( $partials_scanned_map ),
			'posts_scanned' => count( $posts_scanned_map ),
			'images_scanned' => count( $images_scanned_map ),
		);
	}

	/**
	 * @param string $post_type Post type slug.
	 * @param string $slug Post name (slug).
	 * @param bool $published_only When true, only published posts match.
	 *
	 * @return WP_Post|null
	 */
	public static function query_post_by_slug( string $post_type, string $slug, bool $published_only ): ?WP_Post {
		$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );
		if ( $published_only ) {
			$statuses = array( 'publish' );
		}
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
	 * Featured image drift for detect (compares WP featured image with content-manifest.json media_bindings).
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return list<string>
	 */
	private static function describe_featured_image_drift_for_detect( WP_Post $post ): array {
		if ( ! SyncConfig::is_media_sync_enabled() ) {
			return array();
		}

		$manifest_bindings = ContentMediaSync::load_manifest_media_bindings();
		$manifest_media_file = '';
		foreach ( $manifest_bindings as $binding ) {
			if ( is_array( $binding )
				&& isset( $binding['target'] ) && 'featured' === $binding['target']
				&& isset( $binding['post_type'] ) && $post->post_type === (string) $binding['post_type']
				&& isset( $binding['slug'] ) && $post->post_name === (string) $binding['slug']
			) {
				$manifest_media_file = trim( (string) ( $binding['media_filename'] ?? '' ) );
				break;
			}
		}

		$thumbnail_id = (int) get_post_thumbnail_id( $post->ID );
		$wp_media_file = '';
		if ( $thumbnail_id > 0 ) {
			$wp_media_file = ContentMediaSync::ensure_attachment_has_media_path( $thumbnail_id );
		}

		if ( $manifest_media_file !== $wp_media_file ) {
			if ( '' === $wp_media_file && '' !== $manifest_media_file ) {
				return array(
					sprintf(
						/* translators: %s: relative media file path */
						__( 'Featured image set in content-manifest.json (%s) is missing from WordPress.', \ASC_AI_PLUGIN_DOMAIN ),
						$manifest_media_file
					),
				);
			}
			if ( '' !== $wp_media_file && '' === $manifest_media_file ) {
				return array(
					sprintf(
						/* translators: %s: relative media file path */
						__( 'Featured image in WordPress (%s) is missing from content-manifest.json bindings.', \ASC_AI_PLUGIN_DOMAIN ),
						$wp_media_file
					),
				);
			}
			return array(
				sprintf(
					/* translators: 1: WP image path, 2: manifest image path */
					__( 'Featured image differs between WordPress (%1$s) and content-manifest.json (%2$s).', \ASC_AI_PLUGIN_DOMAIN ),
					$wp_media_file,
					$manifest_media_file
				),
			);
		}

		return array();
	}

	/**
	 * Read-only: compare plugin HTML, export manifest metadata (same slice as export “manifest refresh”), publication date,
	 * featured image bindings, and whether on-disk HTML needs whitespace normalization to match export canonical form.
	 *
	 * Note: This function builds the summary counts (pages/partials/posts scanned) inline during its
	 * iteration pass as an optimization so it doesn't have to iterate all files twice.
	 * The counting logic MUST remain synchronized with `get_sync_summary_totals()`.
	 *
	 * @return array{
	 *   in_sync: bool,
	 *   differences: list<array{
	 *     relative_path: string,
	 *     issues: list<string>,
	 *     suggestion: string,
	 *     suggestion_note: string,
	 *     file_modified_gmt: string,
	 *     wp_modified_gmt: string,
	 *     is_minor_summary?: bool
	 *   }>,
	 *   summary: array{
	 *     pages_scanned: int,
	 *     posts_scanned: int,
	 *     images_scanned: int
	 *   },
	 *   checked_at: string
	 * }
	 */
	public static function run_detect_content_differences(): array {
		ContentManifest::invalidate_content_manifest_cache();

		$differences = array();
		$orphan_paths = array();

		$pages_scanned_map = array();
		$partials_scanned_map = array();
		$posts_scanned_map = array();
		$images_scanned_map = array();

		foreach ( ContentExporter::collect_orphan_wordpress_posts() as $row ) {
			$post_id = (int) $row['post_id'];
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$relative_path = (string) $row['relative_path'];
			$wp_modified = get_post_modified_time( 'c', true, $post );
			$wp_iso = '';
			if ( is_string( $wp_modified ) ) {
				$wp_iso = $wp_modified;
			}

			if ( 'asc_boiler_partial' === $post->post_type ) {
				$partials_scanned_map[ $relative_path ] = true;
			} elseif ( 'page' === $post->post_type ) {
				$pages_scanned_map[ $relative_path ] = true;
			} else {
				$posts_scanned_map[ $relative_path ] = true;
			}

			$differences[] = array(
				'relative_path' => $relative_path,
				'issues' => array(
					sprintf(
						/* translators: 1: post title, 2: relative plugin path */
						__( 'Published WordPress content "%1$s" has no matching plugin file on disk (%2$s).', \ASC_AI_PLUGIN_DOMAIN ),
						get_the_title( $post ),
						$relative_path
					),
				),
				'suggestion' => 'export',
				'suggestion_note' => __( 'Suggested: Export to plugin files to write the missing HTML.', \ASC_AI_PLUGIN_DOMAIN ),
				'file_modified_gmt' => '',
				'wp_modified_gmt' => $wp_iso,
			);
		}

		foreach ( ContentExporter::collect_orphan_plugin_files() as $row ) {
			$type_key = (string) $row['type'];
			$filename = (string) $row['filename'];
			$relative_path = (string) $row['relative_path'];
			$orphan_paths[ $relative_path ] = true;

			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$partials_scanned_map[ $relative_path ] = true;
			} elseif ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
				$pages_scanned_map[ $relative_path ] = true;
			} else {
				$posts_scanned_map[ $relative_path ] = true;
			}

			$absolute = self::get_content_type_directory( $type_key ) . $filename;
			$ft = false;
			if ( is_file( $absolute ) ) {
				$ft = filemtime( $absolute );
			}
			$file_iso = '';
			if ( false !== $ft ) {
				$file_iso = gmdate( 'c', (int) $ft );
			}

			$differences[] = array(
				'relative_path' => $relative_path,
				'issues' => array(
					__( 'Plugin export file exists; no matching published WordPress content for this file.', \ASC_AI_PLUGIN_DOMAIN ),
				),
				'suggestion' => 'import',
				'suggestion_note' => __( 'Suggested: Import from plugin files (or publish matching content in WordPress).', \ASC_AI_PLUGIN_DOMAIN ),
				'file_modified_gmt' => $file_iso,
				'wp_modified_gmt' => '',
			);
		}

		$normalization_count = 0;

		foreach ( ContentImporter::collect_import_file_jobs() as $job ) {
			$type_key = (string) $job['type'];
			$filename = (string) $job['filename'];
			$relative_path = self::relative_content_type_file_path( $type_key, $filename );

			if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
				$partials_scanned_map[ $relative_path ] = true;
			} elseif ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
				$pages_scanned_map[ $relative_path ] = true;
			} else {
				$posts_scanned_map[ $relative_path ] = true;
			}

			if ( isset( $orphan_paths[ $relative_path ] ) ) {
				continue;
			}

			$absolute = self::get_content_type_directory( $type_key ) . $filename;
			if ( ! is_file( $absolute ) ) {
				continue;
			}

			$post = self::find_post_for_filename( $type_key, $filename, true );
			if ( null === $post ) {
				continue;
			}

			$markup = self::read_content_markup( $type_key, $filename );
			$body_differs = ! self::markup_is_in_sync( $markup, (string) $post->post_content );
			$issues = array();

			if ( $body_differs ) {
				$issues[] = __( 'Post body HTML differs from the plugin file (normalized comparison).', \ASC_AI_PLUGIN_DOMAIN );
			}

			$manifest_entry = ContentManifest::get_manifest_entry_for_file( $type_key, $filename, $post );
			$issues = array_merge( $issues, self::describe_paired_manifest_drift_for_detect( $type_key, $post, $manifest_entry ) );
			$issues = array_merge( $issues, CompanionFileSync::describe_companion_file_drift_for_detect( $type_key, $post, $filename ) );
			$issues = array_merge( $issues, self::describe_featured_image_drift_for_detect( $post ) );

			$file_whitespace_drift = ContentExporter::plugin_file_needs_whitespace_normalization( $absolute, (string) $post->post_content );

			if ( array() === $issues ) {
				if ( $file_whitespace_drift ) {
					$normalization_count++;
				}
				continue;
			}

			$ft = filemtime( $absolute );
			$file_ts = 0;
			if ( false !== $ft ) {
				$file_ts = (int) $ft;
			}
			$wp_ts = (int) get_post_modified_time( 'U', true, $post );

			$file_iso = '';
			if ( $file_ts > 0 ) {
				$file_iso = gmdate( 'c', $file_ts );
			}
			$wp_iso = '';
			if ( $wp_ts > 0 ) {
				$wp_iso = gmdate( 'c', $wp_ts );
			}

			if ( ! $body_differs ) {
				$manifest_metadata_drift = ContentManifest::export_manifest_row_differs_from_post( $type_key, $filename, $post );
				if ( $file_whitespace_drift || $manifest_metadata_drift ) {
					$suggestion = 'export';
					if ( $file_whitespace_drift && $manifest_metadata_drift ) {
						$suggestion_note = __(
							'Suggested: Export to plugin files — updates content-manifest.json and normalizes plugin HTML on disk. Plugin HTML already matches WordPress.',
							\ASC_AI_PLUGIN_DOMAIN
						);
					} elseif ( $file_whitespace_drift ) {
						$suggestion_note = __(
							'Suggested: Export to plugin files — rewrites plugin HTML to canonical export form on disk.',
							\ASC_AI_PLUGIN_DOMAIN
						);
					} else {
						$suggestion_note = __(
							'Suggested: Export to plugin files — updates content-manifest.json with latest metadata from WordPress. Plugin HTML already matches WordPress.',
							\ASC_AI_PLUGIN_DOMAIN
						);
					}
				} else {
					$suggestion = 'export';
					$suggestion_note = __(
						'Suggested: Export to plugin files — updates content-manifest.json. Plugin HTML already matches WordPress.',
						\ASC_AI_PLUGIN_DOMAIN
					);
				}
			} else {
				$suggestion = 'unclear';
				if ( $file_ts > $wp_ts ) {
					$suggestion = 'import';
					$suggestion_note = sprintf(
						/* translators: 1: file modified (ISO 8601), 2: WordPress modified (ISO 8601) */
						__( 'Suggested: Import from plugin files — the export file is newer (%1$s) than WordPress (%2$s).', \ASC_AI_PLUGIN_DOMAIN ),
						$file_iso,
						$wp_iso
					);
				} elseif ( $wp_ts > $file_ts ) {
					$suggestion = 'export';
					$suggestion_note = sprintf(
						/* translators: 1: WordPress modified (ISO 8601), 2: file modified (ISO 8601) */
						__( 'Suggested: Export to plugin files — WordPress is newer (%1$s) than the export file (%2$s).', \ASC_AI_PLUGIN_DOMAIN ),
						$wp_iso,
						$file_iso
					);
				} else {
					$suggestion_note = sprintf(
						/* translators: %s: ISO 8601 datetime (both sides match) */
						__( 'Post body or manifest fields differ, but last modified times match (%s). Review and choose export or import.', \ASC_AI_PLUGIN_DOMAIN ),
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

		if ( SyncConfig::is_media_sync_enabled() ) {
			foreach ( ContentMediaSync::list_media_files() as $media_rel ) {
				$images_scanned_map[ ContentMediaSync::MEDIA_RELATIVE_DIR . $media_rel ] = true;
			}
			foreach ( ContentMediaSync::collect_attachment_ids_for_export() as $attachment_id ) {
				$media_rel = trim( (string) get_post_meta( $attachment_id, ContentMediaSync::META_MEDIA_PATH, true ) );
				if ( '' !== $media_rel ) {
					$images_scanned_map[ ContentMediaSync::MEDIA_RELATIVE_DIR . $media_rel ] = true;
				}
			}

			foreach ( ContentMediaSync::detect_differences() as $media_diff ) {
				$differences[] = $media_diff;
			}
		}

		if ( $normalization_count > 0 ) {
			$minor_issues = array();
			$minor_issues[] = sprintf(
				/* translators: %d: number of files */
				_n(
					'%d file needs whitespace/formatting normalization on disk.',
					'%d files need whitespace/formatting normalization on disk.',
					$normalization_count,
					\ASC_AI_PLUGIN_DOMAIN
				),
				$normalization_count
			);

			$differences[] = array(
				'relative_path' => __( 'Minor File Adjustments', \ASC_AI_PLUGIN_DOMAIN ),
				'is_minor_summary' => true,
				'issues' => $minor_issues,
				'suggestion' => 'import',
				'suggestion_note' => __( 'Suggested: Run import to normalize plugin HTML files on disk. (No content, metadata, or featured image differences)', \ASC_AI_PLUGIN_DOMAIN ),
				'file_modified_gmt' => '',
				'wp_modified_gmt' => '',
			);
		}

		usort(
			$differences,
			static function ( array $a, array $b ): int {
				$is_minor_a = ! empty( $a['is_minor_summary'] );
				$is_minor_b = ! empty( $b['is_minor_summary'] );
				if ( $is_minor_a !== $is_minor_b ) {
					if ( $is_minor_a ) {
						return 1;
					}
					return -1;
				}
				return strcmp( (string) $a['relative_path'], (string) $b['relative_path'] );
			}
		);

		$summary = array(
			'pages_scanned' => count( $pages_scanned_map ),
			'partials_scanned' => count( $partials_scanned_map ),
			'posts_scanned' => count( $posts_scanned_map ),
			'images_scanned' => count( $images_scanned_map ),
		);

		return array(
			'in_sync' => array() === $differences,
			'differences' => $differences,
			'summary' => $summary,
			'checked_at' => gmdate( 'c' ),
		);
	}

	/**
	 * Manifest drift for a paired file/post: publication date plus the same metadata slice export uses for “manifest refresh” ({@see ContentManifest::manifest_row_metadata_snapshot_for_compare()}).
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

		$desired = ContentManifest::build_export_manifest_row_from_post( $type_key, $post );
		if ( null === $desired ) {
			return $lines;
		}

		if ( ContentManifest::manifest_row_metadata_snapshot_for_compare( $desired )
			!== ContentManifest::manifest_row_metadata_snapshot_for_compare( $manifest_entry ) ) {
			$lines[] = __(
				'content-manifest.json metadata for this file does not match WordPress (title, slug, filename, categories, tags, excerpt, meta description, social title, x title, or focus keyphrase).',
				\ASC_AI_PLUGIN_DOMAIN
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

		$date_rfc = (string) ( $manifest_entry['date_gmt'] ?? '' );
		$pub_mysql = ContentManifest::manifest_rfc3339_to_mysql_gmt( $date_rfc );
		if ( '' === $pub_mysql ) {
			return array();
		}

		if ( ContentManifest::post_publication_gmt_matches_mysql( $post, $pub_mysql ) ) {
			return array();
		}

		return array( __( 'Publication date (GMT) differs between WordPress and the export manifest.', \ASC_AI_PLUGIN_DOMAIN ) );
	}

	/**
	 * Build category and post_tag payloads for a manifest row (slug + name per term).
	 *
	 * @return array<string, list<array{slug:string, name:string}>>
	 */
	public static function manifest_taxonomy_lists_for_post( WP_Post $post ): array {
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
	public static function apply_manifest_taxonomies_from_manifest_entry(
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

			$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
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
							__( 'Could not add %1$s term "%2$s" for %3$s: %4$s', \ASC_AI_PLUGIN_DOMAIN ),
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
				__( 'Could not read %1$s on %2$s: %3$s', \ASC_AI_PLUGIN_DOMAIN ),
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
				__( 'Could not set %1$s on %2$s: %3$s', \ASC_AI_PLUGIN_DOMAIN ),
				$taxonomy,
				$relative_path,
				$replace_result->get_error_message()
			);
			return false;
		}

		return true;
	}

	/**
	 * Query `publish` posts for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return list<WP_Post>
	 */
	public static function query_posts_for_type( string $post_type ): array {
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
	public static function derive_filename_for_post( string $type_key, WP_Post $post ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			$partial_key = trim( (string) get_post_meta( (int) $post->ID, '_asc_ai_boiler_partial_key', true ) );
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
	 * Filter to change the sync content directory path.
	 */
	public const FILTER_CONTENT_DIR = 'asc_ai_boiler_content_dir';

	/**
	 * Filter to change the sync content directory public URL.
	 */
	public const FILTER_CONTENT_URL = 'asc_ai_boiler_content_url';

	/**
	 * Filter to change the sync content type keys.
	 */
	public const FILTER_SYNC_CONTENT_TYPE_KEYS = 'asc_ai_boiler_sync_content_type_keys';

	/**
	 * Available content type keys (e.g. pages, partials, posts).
	 *
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
		return $filtered;
	}

	/**
	 * Absolute path to the `content/` directory (trailing slash). Filtered by {@see FILTER_CONTENT_DIR}.
	 */
	public static function get_content_directory(): string {
		$paths = SyncConfig::get_companion_paths();
		$default = plugin_dir_path( \ASC_AI_PLUGIN_FILE ) . SyncConfig::CONTENT_RELATIVE_ROOT;
		if ( $paths && isset( $paths['content_dir'] ) ) {
			$default = $paths['content_dir'];
		}
		$dir = (string) apply_filters( self::FILTER_CONTENT_DIR, $default );
		$dir = trim( $dir );
		if ( '' === $dir ) {
			return trailingslashit( $default );
		}
		return trailingslashit( $dir );
	}

	/**
	 * Public URL of the `content/` directory (trailing slash). Filtered by {@see FILTER_CONTENT_URL}.
	 */
	public static function get_content_url(): string {
		$paths = SyncConfig::get_companion_paths();
		$default = plugin_dir_url( \ASC_AI_PLUGIN_FILE ) . SyncConfig::CONTENT_RELATIVE_ROOT;
		if ( $paths && isset( $paths['content_url'] ) ) {
			$default = $paths['content_url'];
		}
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
	 * Normalize markup read from disk: no BOM, Unix line endings (matches import/compare expectations).
	 */
	public static function normalize_content_markup_from_disk( string $markup ): string {
		$markup = self::strip_utf8_bom( $markup );

		return str_replace( array( "\r\n", "\r" ), "\n", $markup );
	}

	/**
	 * Canonical HTML for plugin export files: no BOM, Unix newlines, trim outer whitespace.
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

		$dirs = array(
			SyncConfig::CONTENT_DIR_EXCERPTS,
			SyncConfig::CONTENT_DIR_META_DESCRIPTIONS,
		);
		if ( SyncConfig::is_yoast_sync() ) {
			$dirs[] = SyncConfig::CONTENT_DIR_SOCIAL_DESCRIPTIONS;
			$dirs[] = SyncConfig::CONTENT_DIR_X_DESCRIPTIONS;
		}

		foreach ( $dirs as $companion_dir ) {
			$dir = CompanionFileSync::get_companion_text_directory( $companion_dir );
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
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
	public static function resolve_content_file_path( string $type, string $filename ): string {
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
	 * Partial import filenames: every row in content-manifest.json `types.partials` (authoritative).
	 *
	 * @return list<string>
	 */
	public static function collect_partial_filenames_from_manifest(): array {
		$names = array();
		$manifest_types = ContentManifest::load_content_manifest_types();
		if ( isset( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] )
			&& is_array( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] ) ) {
			foreach ( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$fn = ContentManifest::manifest_entry_resolve_filename( $entry );
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
	public static function ensure_partial_posts_from_manifest(): void {
		$manifest_types = ContentManifest::load_content_manifest_types();
		if ( ! isset( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] )
			|| ! is_array( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] ) ) {
			return;
		}

		foreach ( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$filename = ContentManifest::manifest_entry_resolve_filename( $entry );
			if ( '' === $filename || ! self::is_valid_content_filename( $filename ) ) {
				continue;
			}

			if ( self::find_post_for_filename( SyncConfig::CONTENT_TYPE_PARTIALS, $filename, false ) instanceof WP_Post ) {
				continue;
			}

			$partial_key = ContentImporter::expected_partial_key_for_file( $filename, $entry );
			if ( '' === $partial_key ) {
				continue;
			}

			$title = ContentManifest::manifest_title_for_create( $entry, $filename );
			if ( '' === $title ) {
				$title = ContentSyncProfile::title_fallback_from_slug( str_replace( '_', '-', $partial_key ) );
			}
			if ( '' === $title ) {
				continue;
			}

			ContentImporter::create_partial_shell_post_if_missing( $partial_key, $title );
		}

		ContentSyncProfile::invalidate_cache();
	}

	/**
	 * Atomically write file contents with an exclusive lock.
	 *
	 * @return bool
	 */
	public static function write_file_atomically( string $target, string $contents ): bool {
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

		return true;
	}

	/**
	 * Find the WP post that the given (type, filename) pair refers to.
	 *
	 * @param string $type_key Content type key.
	 * @param string $filename Filename (basename).
	 * @param bool $published_only Whether to only search for published posts.
	 *
	 * @return WP_Post|null
	 */
	public static function find_post_for_filename( string $type_key, string $filename, bool $published_only = true ): ?WP_Post {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return ContentImporter::find_post_for_partial_filename( $filename, $published_only );
		}

		if ( SyncConfig::CONTENT_TYPE_PAGES === $type_key ) {
			$page_body_map = ContentSyncProfile::page_body_map();
			if ( isset( $page_body_map[ $filename ] ) ) {
				$resolved = ContentImporter::resolve_page_post( $page_body_map[ $filename ] );
				if ( $resolved instanceof WP_Post ) {
					if ( $published_only && 'publish' !== $resolved->post_status ) {
						return null;
					}
					return $resolved;
				}
				return null;
			}
		}

		$slug = ContentSyncProfile::filename_to_slug( $filename );
		if ( '' === $slug ) {
			return null;
		}

		$sync_types = ContentSyncProfile::sync_types();
		if ( ! isset( $sync_types[ $type_key ] ) ) {
			return null;
		}

		$post_type = $sync_types[ $type_key ]['post_type'];

		$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );
		if ( $published_only ) {
			$statuses = array( 'publish' );
		}
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
	 * Whether file and database bodies match (ignores UTF-8 BOM, CRLF vs LF, outer trim).
	 *
	 * @param string $file_markup File markup.
	 * @param string $db_markup Post content.
	 *
	 * @return bool
	 */
	public static function markup_is_in_sync( string $file_markup, string $db_markup ): bool {
		return self::normalize_markup_for_storage( $file_markup ) === self::normalize_markup_for_storage( wp_unslash( $db_markup ) );
	}

	// -------------------------------------------------------------------------
	// Companion text files (excerpts, meta-descriptions)
	// -------------------------------------------------------------------------

}
