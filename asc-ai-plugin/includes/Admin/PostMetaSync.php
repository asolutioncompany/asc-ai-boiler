<?php
/**
 * Custom post meta synchronization between WordPress and content-manifest.json.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Handles custom post meta synchronization.
 */
final class PostMetaSync {

	/**
	 * Filter hook for registering custom post meta keys to sync.
	 */
	public const FILTER_POST_META_SYNC_KEYS = 'asc_ai_boiler_post_meta_sync_keys';

	/**
	 * Media file extensions recognized for slug/media resolution.
	 */
	private const MEDIA_EXTENSIONS = array(
		// Images & Icons
		'jpg',
		'jpeg',
		'png',
		'gif',
		'webp',
		'svg',
		'avif',
		'bmp',
		'ico',
		'tif',
		'tiff',
		'heic',
		'heif',
		'raw',
		// Documents & Spreadsheets
		'pdf',
		'doc',
		'docx',
		'xls',
		'xlsx',
		'ppt',
		'pptx',
		'csv',
		'rtf',
		'odt',
		'ods',
		'odp',
		'pages',
		'numbers',
		'key',
		// Audio
		'mp3',
		'm4a',
		'ogg',
		'wav',
		'wma',
		'flac',
		'aac',
		'mid',
		'midi',
		// Video
		'mp4',
		'm4v',
		'mov',
		'wmv',
		'avi',
		'mpg',
		'mpeg',
		'ogv',
		'webm',
		'mkv',
		'flv',
		'3gp',
		// Archives & Packages
		'zip',
		'gz',
		'tar',
		'7z',
		'rar',
	);

	/**
	 * Get registered post meta sync key configurations from active site layer filters.
	 *
	 * @return list<array{
	 *   meta_key: string,
	 *   post_types: list<string>,
	 *   type: 'raw'|'slug'
	 * }>
	 */
	public static function get_registered_meta_keys(): array {
		$raw_keys = apply_filters( self::FILTER_POST_META_SYNC_KEYS, array() );
		if ( ! is_array( $raw_keys ) ) {
			return array();
		}

		$valid = array();
		foreach ( $raw_keys as $item ) {
			if ( ! is_array( $item ) || empty( $item['meta_key'] ) ) {
				continue;
			}

			$meta_key = sanitize_key( (string) $item['meta_key'] );
			if ( '' === $meta_key ) {
				continue;
			}

			$post_types = array();
			if ( isset( $item['post_types'] ) && is_array( $item['post_types'] ) ) {
				foreach ( $item['post_types'] as $pt ) {
					$clean_pt = sanitize_key( (string) $pt );
					if ( '' !== $clean_pt ) {
						$post_types[] = $clean_pt;
					}
				}
			}

			$type = 'raw';
			if ( isset( $item['type'] ) ) {
				$t = strtolower( trim( (string) $item['type'] ) );
				if ( 'slug' === $t || 'media_csv' === $t ) {
					$type = 'slug';
				}
			}

			$valid[] = array(
				'meta_key'   => $meta_key,
				'post_types' => array_values( array_unique( $post_types ) ),
				'type'       => $type,
			);
		}

		return $valid;
	}

	/**
	 * Check if a filename or slug string has a recognized media file extension.
	 *
	 * @param string $filename_or_slug Value to test.
	 * @return bool
	 */
	public static function is_media_extension( string $filename_or_slug ): bool {
		$ext = strtolower( pathinfo( trim( $filename_or_slug ), PATHINFO_EXTENSION ) );
		return in_array( $ext, self::MEDIA_EXTENSIONS, true );
	}

	/**
	 * Reverse-resolve numeric WordPress ID(s) to portable filenames or post slugs.
	 *
	 * @param string $raw_ids Comma-separated list or single ID.
	 * @param list<string> $post_types Target post types for context.
	 * @return string Comma-separated portable slugs/filenames.
	 */
	public static function resolve_ids_to_slugs( string $raw_ids, array $post_types = array() ): string {
		$parts = explode( ',', $raw_ids );
		$resolved = array();

		foreach ( $parts as $part ) {
			$id = absint( trim( $part ) );
			if ( $id <= 0 ) {
				continue;
			}

			$post_type = get_post_type( $id );
			if ( 'attachment' === $post_type ) {
				$media_rel = (string) get_post_meta( $id, ContentMediaSync::META_MEDIA_PATH, true );
				if ( '' !== trim( $media_rel ) ) {
					$resolved[] = basename( trim( $media_rel ) );
					continue;
				}

				$attached_file = (string) get_post_meta( $id, '_wp_attached_file', true );
				if ( '' !== trim( $attached_file ) ) {
					$resolved[] = basename( trim( $attached_file ) );
					continue;
				}

				$attached_path = get_attached_file( $id );
				if ( is_string( $attached_path ) && '' !== trim( $attached_path ) ) {
					$resolved[] = basename( trim( $attached_path ) );
					continue;
				}
			} elseif ( false !== $post_type ) {
				$post = get_post( $id );
				if ( $post instanceof WP_Post && '' !== (string) $post->post_name ) {
					$resolved[] = (string) $post->post_name;
				}
			}
		}

		return implode( ',', $resolved );
	}

	/**
	 * Resolve portable filenames or post slugs to numeric WordPress ID(s).
	 *
	 * @param string $slugs_str Comma-separated list or single slug/filename.
	 * @param list<string> $post_types Target post types for post resolution.
	 * @return string Comma-separated numeric IDs.
	 */
	public static function resolve_slugs_to_ids( string $slugs_str, array $post_types = array() ): string {
		$parts = explode( ',', $slugs_str );
		$ids = array();

		foreach ( $parts as $part ) {
			$item = trim( $part );
			if ( '' === $item ) {
				continue;
			}

			if ( self::is_media_extension( $item ) ) {
				$attachment_id = ContentMediaSync::find_attachment_id_by_media_path( $item );
				if ( $attachment_id > 0 ) {
					$ids[] = $attachment_id;
					continue;
				}

				// Fallback lookup by _wp_attached_file filename
				global $wpdb;
				$like = '%' . $wpdb->esc_like( $item );
				$found_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
						$like
					)
				);
				if ( $found_id ) {
					$ids[] = absint( $found_id );
				}
			} else {
				$slug = sanitize_title( str_replace( array( '.html', '.txt' ), '', $item ) );
				$search_types = ! empty( $post_types ) ? $post_types : array( 'page', 'post' );
				foreach ( $search_types as $pt ) {
					$post = ContentSync::query_post_by_slug( $pt, $slug, false );
					if ( $post instanceof WP_Post ) {
						$ids[] = (int) $post->ID;
						break;
					}
				}
			}
		}

		return implode( ',', array_values( array_unique( $ids ) ) );
	}

	/**
	 * Build manifest post_meta rows for export.
	 *
	 * @return list<array{
	 *   post_type: string,
	 *   slug: string,
	 *   meta_key: string,
	 *   meta_value: string
	 * }>
	 */
	public static function build_manifest_post_meta_rows(): array {
		$registered_keys = self::get_registered_meta_keys();
		if ( empty( $registered_keys ) ) {
			return array();
		}

		$rows = array();

		foreach ( $registered_keys as $config ) {
			$meta_key = $config['meta_key'];
			$post_types = $config['post_types'];
			$type = $config['type'];

			foreach ( $post_types as $post_type ) {
				$posts = ContentSync::query_posts_for_type( $post_type );
				foreach ( $posts as $post ) {
					if ( ! $post instanceof WP_Post ) {
						continue;
					}

					$slug = (string) $post->post_name;
					if ( '' === $slug ) {
						continue;
					}

					$raw_val = get_post_meta( (int) $post->ID, $meta_key, true );
					if ( '' === $raw_val || false === $raw_val || null === $raw_val || '0' === $raw_val || 0 === $raw_val ) {
						continue;
					}

					$val_str = is_array( $raw_val ) ? implode( ',', $raw_val ) : (string) $raw_val;
					if ( '' === trim( $val_str ) || '0' === trim( $val_str ) ) {
						continue;
					}

					if ( 'slug' === $type ) {
						$manifest_val = self::resolve_ids_to_slugs( $val_str, $post_types );
					} else {
						$manifest_val = trim( $val_str );
					}

					if ( '' === $manifest_val || '0' === $manifest_val ) {
						continue;
					}

					$rows[] = array(
						'post_type'  => $post_type,
						'slug'       => $slug,
						'meta_key'   => $meta_key,
						'meta_value' => $manifest_val,
					);
				}
			}
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$pt_cmp = strcmp( $a['post_type'], $b['post_type'] );
				if ( 0 !== $pt_cmp ) {
					return $pt_cmp;
				}
				$slug_cmp = strcmp( $a['slug'], $b['slug'] );
				if ( 0 !== $slug_cmp ) {
					return $slug_cmp;
				}
				return strcmp( $a['meta_key'], $b['meta_key'] );
			}
		);

		return $rows;
	}

	/**
	 * Apply custom post meta from manifest to WordPress database during import.
	 *
	 * @param list<string> $messages Log accumulator.
	 * @return void
	 */
	public static function import_post_meta_from_manifest( array &$messages ): void {
		$manifest_rows   = ContentManifest::load_content_manifest_post_meta();
		$registered_keys = self::get_registered_meta_keys();
		if ( empty( $manifest_rows ) && empty( $registered_keys ) ) {
			return;
		}

		$registered_map = array();
		foreach ( $registered_keys as $config ) {
			$registered_map[ $config['meta_key'] ] = $config;
		}

		// Build a lookup: [ post_type ][ slug ][ meta_key ] => meta_value
		$manifest_lookup = array();
		foreach ( $manifest_rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['post_type'] ) || empty( $row['slug'] ) || empty( $row['meta_key'] ) ) {
				continue;
			}
			$pt = (string) $row['post_type'];
			$sl = (string) $row['slug'];
			$mk = (string) $row['meta_key'];
			$manifest_lookup[ $pt ][ $sl ][ $mk ] = (string) ( $row['meta_value'] ?? '' );
		}

		$updated_count   = 0;
		$processed_posts = array();

		// 1. Process all registered keys across all matching posts in WordPress
		foreach ( $registered_keys as $config ) {
			$meta_key   = $config['meta_key'];
			$type       = $config['type'];
			$post_types = $config['post_types'];

			foreach ( $post_types as $post_type ) {
				$posts = ContentSync::query_posts_for_type( $post_type );
				foreach ( $posts as $post ) {
					if ( ! $post instanceof WP_Post ) {
						continue;
					}

					$post_id = (int) $post->ID;
					$slug    = (string) $post->post_name;
					if ( '' === $slug ) {
						continue;
					}

					$processed_posts[ $post_id ][ $meta_key ] = true;

					$manifest_val = trim( $manifest_lookup[ $post_type ][ $slug ][ $meta_key ] ?? '' );

					if ( 'slug' === $type ) {
						$target_val = ( '' !== $manifest_val && '0' !== $manifest_val )
							? self::resolve_slugs_to_ids( $manifest_val, $post_types )
							: '';
					} else {
						$target_val = ( '0' === $manifest_val ) ? '' : $manifest_val;
					}

					$current_val = (string) get_post_meta( $post_id, $meta_key, true );

					if ( '' === $target_val || '0' === $target_val ) {
						// Remove custom post meta if value is 0 or not present in manifest
						if ( '' !== $current_val && '0' !== $current_val ) {
							delete_post_meta( $post_id, $meta_key );
							$updated_count++;
						}
					} else {
						if ( $current_val !== $target_val ) {
							update_post_meta( $post_id, $meta_key, $target_val );
							$updated_count++;
						}
					}
				}
			}
		}

		// 2. Process any remaining manifest rows that might not be in registered_keys
		foreach ( $manifest_rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['post_type'] ) || empty( $row['slug'] ) || empty( $row['meta_key'] ) ) {
				continue;
			}

			$post_type    = (string) $row['post_type'];
			$slug         = (string) $row['slug'];
			$meta_key     = (string) $row['meta_key'];
			$manifest_val = trim( (string) ( $row['meta_value'] ?? '' ) );

			$post = ContentSync::query_post_by_slug( $post_type, $slug, false );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$post_id = (int) $post->ID;
			if ( isset( $processed_posts[ $post_id ][ $meta_key ] ) ) {
				continue;
			}

			$type = self::is_media_extension( $manifest_val ) ? 'slug' : 'raw';
			if ( 'slug' === $type ) {
				$target_val = ( '' !== $manifest_val && '0' !== $manifest_val )
					? self::resolve_slugs_to_ids( $manifest_val, array( $post_type ) )
					: '';
			} else {
				$target_val = ( '0' === $manifest_val ) ? '' : $manifest_val;
			}

			$current_val = (string) get_post_meta( $post_id, $meta_key, true );
			if ( '' === $target_val || '0' === $target_val ) {
				if ( '' !== $current_val && '0' !== $current_val ) {
					delete_post_meta( $post_id, $meta_key );
					$updated_count++;
				}
			} else {
				if ( $current_val !== $target_val ) {
					update_post_meta( $post_id, $meta_key, $target_val );
					$updated_count++;
				}
			}
		}

		if ( $updated_count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of custom post meta entries */
				_n(
					'Synchronized %d custom post meta field from manifest.',
					'Synchronized %d custom post meta fields from manifest.',
					$updated_count,
					\ASC_AI_PLUGIN_DOMAIN
				),
				$updated_count
			);
		}
	}

	/**
	 * Detect if custom post meta differs between WordPress and content-manifest.json.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $slug Post name (slug).
	 * @param int $post_id WordPress post ID.
	 * @return list<string> Issue descriptions.
	 */
	public static function describe_post_meta_drift_for_detect( string $post_type, string $slug, int $post_id ): array {
		$registered_keys = self::get_registered_meta_keys();
		if ( empty( $registered_keys ) ) {
			return array();
		}

		$manifest_rows = ContentManifest::load_content_manifest_post_meta();
		$manifest_map = array();
		foreach ( $manifest_rows as $row ) {
			if ( is_array( $row )
				&& ( $row['post_type'] ?? '' ) === $post_type
				&& ( $row['slug'] ?? '' ) === $slug
				&& isset( $row['meta_key'] ) ) {
				$manifest_map[ (string) $row['meta_key'] ] = (string) ( $row['meta_value'] ?? '' );
			}
		}

		$issues = array();

		foreach ( $registered_keys as $config ) {
			if ( ! in_array( $post_type, $config['post_types'], true ) ) {
				continue;
			}

			$meta_key   = $config['meta_key'];
			$type       = $config['type'];
			$raw_wp_val = (string) get_post_meta( $post_id, $meta_key, true );

			if ( 'slug' === $type ) {
				$wp_val = self::resolve_ids_to_slugs( $raw_wp_val, $config['post_types'] );
			} else {
				$wp_val = trim( $raw_wp_val );
			}
			if ( '0' === $wp_val ) {
				$wp_val = '';
			}

			$manifest_val = trim( $manifest_map[ $meta_key ] ?? '' );
			if ( '0' === $manifest_val ) {
				$manifest_val = '';
			}

			if ( $wp_val !== $manifest_val ) {
				$issues[] = sprintf(
					/* translators: %s: custom meta key name */
					__( 'Custom post meta "%s" differs from content-manifest.json.', \ASC_AI_PLUGIN_DOMAIN ),
					$meta_key
				);
			}
		}

		return $issues;
	}
}
