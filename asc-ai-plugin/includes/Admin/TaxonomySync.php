<?php

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TaxonomySync {

	public static function get_supported_taxonomies(): array {
		$taxonomies = array( 'category', 'post_tag' );
		$filtered = apply_filters( 'asc_ai_boiler_manifest_taxonomies', $taxonomies );
		if ( ! is_array( $filtered ) ) {
			return $taxonomies;
		}

		$out = array();
		foreach ( $filtered as $taxonomy ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
				$out[] = $taxonomy;
			}
		}

		return array_values( array_unique( $out ) );
	}

	public static function load_manifest_taxonomies(): array {
		$data = ContentManifest::load_content_manifest();
		if ( ! is_array( $data ) || ! isset( $data['taxonomies'] ) || ! is_array( $data['taxonomies'] ) ) {
			return array();
		}

		$out = array();
		foreach ( self::get_supported_taxonomies() as $taxonomy ) {
			$rows = $data['taxonomies'][ $taxonomy ] ?? null;
			if ( ! is_array( $rows ) ) {
				continue;
			}

			$out[ $taxonomy ] = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
				if ( '' === $slug ) {
					continue;
				}
				$row['slug'] = $slug;
				$out[ $taxonomy ][] = $row;
			}
		}

		return $out;
	}

	public static function import_manifest_taxonomies( array &$messages ): bool {
		$changed = false;
		foreach ( self::load_manifest_taxonomies() as $taxonomy => $rows ) {
			foreach ( $rows as $row ) {
				$slug = (string) $row['slug'];
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term instanceof \WP_Term ) {
					$name = sanitize_text_field( (string) ( $row['name'] ?? $slug ) );
					if ( '' === $name ) {
						$name = $slug;
					}
					$args = array( 'slug' => $slug );
					if ( array_key_exists( 'description', $row ) ) {
						$args['description'] = wp_kses_post( (string) $row['description'] );
					}
					$result = wp_insert_term( $name, $taxonomy, $args );
					if ( ! is_wp_error( $result ) ) {
						$changed = true;
					}
					continue;
				}

				$args = array();
				if ( array_key_exists( 'name', $row ) ) {
					$name = sanitize_text_field( (string) $row['name'] );
					if ( '' !== $name && $name !== $term->name ) {
						$args['name'] = $name;
					}
				}
				if ( array_key_exists( 'description', $row ) ) {
					$description = wp_kses_post( (string) $row['description'] );
					if ( $description !== $term->description ) {
						$args['description'] = $description;
					}
				}
				if ( array() === $args ) {
					continue;
				}
				$result = wp_update_term( (int) $term->term_id, $taxonomy, $args );
				if ( ! is_wp_error( $result ) ) {
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			$messages[] = __( 'Imported taxonomy names and descriptions from content-manifest.json.', \ASC_AI_PLUGIN_DOMAIN );
		}

		return $changed;
	}

	public static function build_export_manifest_taxonomies(): array {
		$term_ids = array();
		$supported = self::get_supported_taxonomies();
		foreach ( $supported as $taxonomy ) {
			$term_ids[ $taxonomy ] = array();
		}

		foreach ( ContentSyncProfile::all_sync_types() as $type_key => $type_config ) {
			if ( ! SyncConfig::is_content_type_enabled( (string) $type_key ) ) {
				continue;
			}
			$post_type = (string) ( $type_config['post_type'] ?? '' );
			foreach ( ContentSync::query_posts_for_type( $post_type ) as $post ) {
				foreach ( $supported as $taxonomy ) {
					if ( ! is_object_in_taxonomy( (string) $post->post_type, $taxonomy ) ) {
						continue;
					}
					$terms = get_the_terms( (int) $post->ID, $taxonomy );
					if ( ! is_array( $terms ) ) {
						continue;
					}
					foreach ( $terms as $term ) {
						if ( $term instanceof \WP_Term ) {
							$term_ids[ $taxonomy ][ (int) $term->term_id ] = true;
						}
					}
				}
			}
		}

		$out = array();
		foreach ( $term_ids as $taxonomy => $ids ) {
			$rows = array();
			foreach ( array_keys( $ids ) as $term_id ) {
				$term = get_term( (int) $term_id, $taxonomy );
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$rows[] = array(
					'slug' => (string) $term->slug,
					'name' => (string) $term->name,
					'description' => (string) $term->description,
				);
			}
			usort(
				$rows,
				static function ( array $first, array $second ): int {
					return strcmp( $first['slug'], $second['slug'] );
				}
			);
			if ( array() !== $rows ) {
				$out[ $taxonomy ] = $rows;
			}
		}

		return $out;
	}

	public static function has_enabled_content_sources(): bool {
		foreach ( ContentSyncProfile::all_sync_types() as $type_key => $type_config ) {
			if ( ! SyncConfig::is_content_type_enabled( (string) $type_key ) ) {
				continue;
			}
			$post_type = (string) ( $type_config['post_type'] ?? '' );
			foreach ( self::get_supported_taxonomies() as $taxonomy ) {
				if ( is_object_in_taxonomy( $post_type, $taxonomy ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function describe_manifest_drift(): array {
		$issues = array();
		foreach ( self::load_manifest_taxonomies() as $taxonomy => $rows ) {
			foreach ( $rows as $row ) {
				$term = get_term_by( 'slug', (string) $row['slug'], $taxonomy );
				if ( ! $term instanceof \WP_Term ) {
					$issues[] = sprintf(
						__( 'The %1$s term "%2$s" is missing from WordPress.', \ASC_AI_PLUGIN_DOMAIN ),
						$taxonomy,
						(string) $row['slug']
					);
					continue;
				}
				if ( array_key_exists( 'name', $row ) && sanitize_text_field( (string) $row['name'] ) !== $term->name ) {
					$issues[] = sprintf( __( 'The %1$s term "%2$s" name differs from content-manifest.json.', \ASC_AI_PLUGIN_DOMAIN ), $taxonomy, (string) $row['slug'] );
				}
				if ( array_key_exists( 'description', $row ) && wp_kses_post( (string) $row['description'] ) !== $term->description ) {
					$issues[] = sprintf( __( 'The %1$s term "%2$s" description differs from content-manifest.json.', \ASC_AI_PLUGIN_DOMAIN ), $taxonomy, (string) $row['slug'] );
				}
			}
		}

		return $issues;
	}

	public static function detect_differences(): array {
		$issues = self::describe_manifest_drift();
		if ( array() === $issues ) {
			return array();
		}

		return array(
			array(
				'relative_path' => 'content/content-manifest.json taxonomies',
				'issues' => $issues,
				'suggestion' => 'unclear',
				'suggestion_note' => __( 'Taxonomy terms do not store modification times. Review the values, then import from the manifest or export from WordPress.', \ASC_AI_PLUGIN_DOMAIN ),
				'file_modified_gmt' => '',
				'wp_modified_gmt' => '',
			),
		);
	}
}
