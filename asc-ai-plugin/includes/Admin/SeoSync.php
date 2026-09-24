<?php
/**
 * Provider-aware SEO content synchronization.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WP_Post;

final class SeoSync {

	public const FILTER_PROVIDER = 'asc_ai_boiler_seo_provider';

	public const FILTER_FIELD_MAP = 'asc_ai_boiler_seo_field_map';

	public const PROVIDER_YOAST = 'yoast';

	/**
	 * @return array<string, array{provider:string, meta_key:string, provider_key:string, source:string, directory?:string}>
	 */
	public static function get_field_map(): array {
		$provider = (string) apply_filters( self::FILTER_PROVIDER, self::PROVIDER_YOAST );
		if ( self::PROVIDER_YOAST === $provider && ! SyncConfig::is_yoast_sync() ) {
			return array();
		}

		$fields = array();
		if ( self::PROVIDER_YOAST === $provider ) {
			$fields = array(
				'meta_description' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_metadesc',
					'provider_key' => 'metadesc',
					'source' => 'companion',
					'directory' => SyncConfig::CONTENT_DIR_META_DESCRIPTIONS,
				),
				'social_description' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_opengraph-description',
					'provider_key' => 'opengraph-description',
					'source' => 'companion',
					'directory' => SyncConfig::CONTENT_DIR_SOCIAL_DESCRIPTIONS,
				),
				'x_description' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_twitter-description',
					'provider_key' => 'twitter-description',
					'source' => 'companion',
					'directory' => SyncConfig::CONTENT_DIR_X_DESCRIPTIONS,
				),
				'social_title' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_opengraph-title',
					'provider_key' => 'opengraph-title',
					'source' => 'inline',
				),
				'x_title' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_twitter-title',
					'provider_key' => 'twitter-title',
					'source' => 'inline',
				),
				'focus_keyphrase' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_focuskw',
					'provider_key' => 'focuskw',
					'source' => 'inline',
				),
				'primary_category' => array(
					'provider' => self::PROVIDER_YOAST,
					'meta_key' => '_yoast_wpseo_primary_category',
					'provider_key' => 'primary_category',
					'source' => 'taxonomy_slug',
				),
			);
		}

		$filtered = apply_filters( self::FILTER_FIELD_MAP, $fields, $provider );
		if ( ! is_array( $filtered ) ) {
			return $fields;
		}

		return $filtered;
	}

	public static function get_value( int $post_id, array $field ): string {
		$provider_key = (string) ( $field['provider_key'] ?? '' );
		$provider = (string) ( $field['provider'] ?? '' );
		if ( self::PROVIDER_YOAST === $provider && class_exists( '\WPSEO_Meta' ) && method_exists( '\WPSEO_Meta', 'get_value' ) && '' !== $provider_key ) {
			return (string) \WPSEO_Meta::get_value( $provider_key, $post_id );
		}

		$meta_key = (string) ( $field['meta_key'] ?? '' );
		return (string) get_post_meta( $post_id, $meta_key, true );
	}

	public static function set_value( int $post_id, array $field, string $value ): bool {
		$current = trim( self::get_value( $post_id, $field ) );
		$value = trim( $value );
		if ( $current === $value ) {
			return false;
		}

		$provider_key = (string) ( $field['provider_key'] ?? '' );
		$provider = (string) ( $field['provider'] ?? '' );
		if ( self::PROVIDER_YOAST === $provider && class_exists( '\WPSEO_Meta' ) && '' !== $provider_key ) {
			if ( '' === $value && method_exists( '\WPSEO_Meta', 'delete' ) ) {
				\WPSEO_Meta::delete( $provider_key, $post_id );
				return true;
			}
			if ( method_exists( '\WPSEO_Meta', 'set_value' ) ) {
				\WPSEO_Meta::set_value( $provider_key, $value, $post_id );
				return true;
			}
		}

		$meta_key = (string) ( $field['meta_key'] ?? '' );
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}

		return true;
	}

	public static function read_manifest_value( string $manifest_field, array $field, array $manifest_entry ): ?string {
		if ( ! array_key_exists( $manifest_field, $manifest_entry ) ) {
			return null;
		}

		$source = (string) ( $field['source'] ?? '' );
		if ( 'inline' === $source || 'taxonomy_slug' === $source ) {
			return trim( (string) $manifest_entry[ $manifest_field ] );
		}

		$filename = basename( trim( (string) $manifest_entry[ $manifest_field ] ) );
		if ( '' === $filename || '.txt' !== substr( $filename, -4 ) ) {
			return null;
		}

		$directory = (string) ( $field['directory'] ?? '' );
		$path = CompanionFileSync::get_companion_text_directory( $directory ) . $filename;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return null;
		}

		return trim( $contents );
	}

	public static function import_manifest_fields( int $post_id, array $manifest_entry, string $relative_path, array &$messages ): bool {
		$changed = false;
		foreach ( self::get_field_map() as $manifest_field => $field ) {
			if ( 'taxonomy_slug' === (string) ( $field['source'] ?? '' ) ) {
				continue;
			}
			$value = self::read_manifest_value( $manifest_field, $field, $manifest_entry );
			if ( null === $value || ! self::set_value( $post_id, $field, $value ) ) {
				continue;
			}

			$changed = true;
			$messages[] = sprintf(
				__( 'Imported SEO field "%1$s" for %2$s.', \ASC_AI_PLUGIN_DOMAIN ),
				$manifest_field,
				$relative_path
			);
		}

		return $changed;
	}

	public static function import_primary_category(
		int $post_id,
		string $post_type,
		array $manifest_entry,
		string $relative_path,
		array &$messages
	): bool {
		$fields = self::get_field_map();
		$field = $fields['primary_category'] ?? null;
		if ( ! is_array( $field ) || ! array_key_exists( 'primary_category', $manifest_entry ) ) {
			return false;
		}
		if ( ! is_object_in_taxonomy( $post_type, 'category' ) ) {
			return false;
		}

		$slug = sanitize_title( (string) $manifest_entry['primary_category'] );
		$value = '';
		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term instanceof \WP_Term || ! has_term( (int) $term->term_id, 'category', $post_id ) ) {
				$messages[] = sprintf(
					__( 'Could not import primary category "%1$s" for %2$s because the category is not assigned to the post.', \ASC_AI_PLUGIN_DOMAIN ),
					$slug,
					$relative_path
				);
				return false;
			}
			$value = (string) $term->term_id;
		}

		if ( ! self::set_value( $post_id, $field, $value ) ) {
			return false;
		}

		$messages[] = sprintf(
			__( 'Imported primary category for %s.', \ASC_AI_PLUGIN_DOMAIN ),
			$relative_path
		);
		return true;
	}

	public static function export_companion_files_for_post( WP_Post $post, string $html_filename ): void {
		foreach ( self::get_field_map() as $field ) {
			if ( 'companion' !== (string) ( $field['source'] ?? '' ) ) {
				continue;
			}

			$value = trim( self::get_value( (int) $post->ID, $field ) );
			$directory = (string) ( $field['directory'] ?? '' );
			CompanionFileSync::write_companion_text_file( $directory, $html_filename, $value );
		}
	}

	public static function append_export_manifest_fields( WP_Post $post, string $html_filename, array &$row ): void {
		$basename = CompanionFileSync::companion_text_basename( $html_filename );
		foreach ( self::get_field_map() as $manifest_field => $field ) {
			$value = trim( self::get_value( (int) $post->ID, $field ) );
			if ( 'taxonomy_slug' === (string) ( $field['source'] ?? '' ) ) {
				if ( ! is_object_in_taxonomy( (string) $post->post_type, 'category' ) ) {
					continue;
				}
				$row[ $manifest_field ] = self::primary_category_slug( $post, $field );
				continue;
			}
			if ( 'companion' === (string) ( $field['source'] ?? '' ) ) {
				$row[ $manifest_field ] = $basename;
				continue;
			}

			$row[ $manifest_field ] = $value;
		}
	}

	/**
	 * @return list<string>
	 */
	public static function describe_manifest_drift( WP_Post $post, array $manifest_entry ): array {
		$issues = array();
		foreach ( self::get_field_map() as $manifest_field => $field ) {
			$value = self::read_manifest_value( $manifest_field, $field, $manifest_entry );
			if ( null === $value ) {
				continue;
			}
			$current = trim( self::get_value( (int) $post->ID, $field ) );
			if ( 'taxonomy_slug' === (string) ( $field['source'] ?? '' ) ) {
				$current = self::primary_category_slug( $post, $field );
				$value = sanitize_title( $value );
			}
			if ( $value === $current ) {
				continue;
			}

			$issues[] = sprintf(
				__( 'SEO field "%s" differs from WordPress.', \ASC_AI_PLUGIN_DOMAIN ),
				$manifest_field
			);
		}

		return $issues;
	}

	private static function primary_category_slug( WP_Post $post, array $field ): string {
		$term_id = (int) self::get_value( (int) $post->ID, $field );
		if ( $term_id <= 0 || ! has_term( $term_id, 'category', (int) $post->ID ) ) {
			return '';
		}

		$term = get_term( $term_id, 'category' );
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		return (string) $term->slug;
	}

}
