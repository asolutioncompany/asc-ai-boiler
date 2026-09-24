<?php
/**
 * Decomposed class.
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use WP_Post;

final class CompanionFileSync {

	/**
	 * Absolute path to a companion text subdirectory (trailing slash).
	 *
	 * @param string $dir_name Subdirectory name (e.g. `excerpts`).
	 *
	 * @return string
	 */
	public static function get_companion_text_directory( string $dir_name ): string {
		return ContentSync::get_content_directory() . $dir_name . '/';
	}

	/**
	 * Derive the `.txt` basename for a companion file from the HTML basename.
	 *
	 * @param string $html_filename HTML basename (e.g. `my-post.html`).
	 *
	 * @return string `.txt` basename, or empty when the HTML filename is invalid.
	 */
	public static function companion_text_basename( string $html_filename ): string {
		if ( '.html' !== substr( $html_filename, -5 ) ) {
			return '';
		}
		return substr( $html_filename, 0, -5 ) . '.txt';
	}

	/**
	 * Read a companion text file. Returns `null` when the file does not exist or is unreadable.
	 *
	 * @param string $dir_name Companion subdirectory name.
	 * @param string $html_filename HTML basename of the paired content file.
	 *
	 * @return string|null Raw file contents, or null when missing.
	 */
	public static function read_companion_text_file( string $dir_name, string $html_filename ): ?string {
		$basename = self::companion_text_basename( $html_filename );
		if ( '' === $basename ) {
			return null;
		}
		$path = self::get_companion_text_directory( $dir_name ) . $basename;
		if ( ! is_file( $path ) ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return null;
		}
		return $raw;
	}

	/**
	 * Write a companion text file atomically. Creates the subdirectory if needed.
	 *
	 * An empty `$text` writes an empty file (explicit "no value" signal for import).
	 *
	 * @param string $dir_name Companion subdirectory name.
	 * @param string $html_filename HTML basename of the paired content file.
	 * @param string $text Plain-text content to store.
	 *
	 * @return bool True on success.
	 */
	public static function write_companion_text_file( string $dir_name, string $html_filename, string $text ): bool {
		$basename = self::companion_text_basename( $html_filename );
		if ( '' === $basename ) {
			return false;
		}
		$dir = self::get_companion_text_directory( $dir_name );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$dir_norm = wp_normalize_path( $dir );
		$target = wp_normalize_path( $dir . $basename );
		if ( 0 !== strpos( $target, $dir_norm ) ) {
			return false;
		}
		return ContentSync::write_file_atomically( $target, $text );
	}

	/**
	 * Delete a companion text file when it exists.
	 *
	 * @param string $dir_name Companion subdirectory name.
	 * @param string $html_filename HTML basename of the paired content file.
	 *
	 * @return bool True when the file was present and removed.
	 */
	public static function delete_companion_text_file( string $dir_name, string $html_filename ): bool {
		$basename = self::companion_text_basename( $html_filename );
		if ( '' === $basename ) {
			return false;
		}
		$dir = self::get_companion_text_directory( $dir_name );
		$dir_norm = wp_normalize_path( $dir );
		$target = wp_normalize_path( $dir . $basename );
		if ( 0 !== strpos( $target, $dir_norm ) ) {
			return false;
		}
		if ( ! file_exists( $target ) || ! is_file( $target ) ) {
			return false;
		}
		return unlink( $target );
	}

	/**
	 * Retrieve a post metadata value directly from the wp_postmeta table,
	 * bypassing any get_post_metadata filters/cache overrides (e.g. from Yoast SEO).
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 *
	 * @return string Metadata value, or empty string if not found.
	 */
	public static function get_post_meta_raw( int $post_id, string $meta_key ): string {
		global $wpdb;
		$val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$meta_key
			)
		);
		if ( is_string( $val ) ) {
			return $val;
		}
		return '';
	}

	/**
	 * Write a post metadata value directly to the wp_postmeta table,
	 * bypassing any update_post_metadata filters/validation overrides (e.g. from Yoast SEO).
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function update_post_meta_raw( int $post_id, string $meta_key, $meta_value ): bool {
		global $wpdb;
		$value = (string) $meta_value;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$meta_key
			)
		);

		if ( null !== $existing ) {
			$result = $wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => $value ),
				array(
					'post_id' => $post_id,
					'meta_key' => $meta_key,
				)
			);
			return false !== $result;
		}

		$result = $wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id' => $post_id,
				'meta_key' => $meta_key,
				'meta_value' => $value,
			)
		);
		if ( false !== $result ) {
			return true;
		}
		return false;
	}

	/**
	 * Write the excerpt companion file for a post during export.
	 *
	 * @param WP_Post $post Post to back up.
	 * @param string $html_filename HTML basename of the content file.
	 *
	 * @return void
	 */
	public static function export_companion_files_for_post( WP_Post $post, string $html_filename ): void {
		$excerpt = trim( (string) $post->post_excerpt );
		self::write_companion_text_file( SyncConfig::CONTENT_DIR_EXCERPTS, $html_filename, $excerpt );
	}

	/**
	 * Apply excerpt and meta description from companion files to a post during import.
	 * Skipped for partials (caller should guard with type_key check).
	 *
	 * A missing companion file means "skip that field." An existing file (even empty) is applied.
	 *
	 * @param int $post_id Post ID to update.
	 * @param string $html_filename HTML basename of the paired content file.
	 * @param string $relative_path Plugin-relative path for log lines.
	 * @param list<string> $messages Messages accumulator.
	 *
	 * @return bool True when any field was updated in the database.
	 */
	public static function import_companion_files_for_post(
		int $post_id,
		string $html_filename,
		string $relative_path,
		array &$messages,
		?array $manifest_entry = null
	): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$changed = false;

		$excerpt_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_EXCERPTS, $html_filename );
		if ( null !== $excerpt_raw ) {
			$file_excerpt = trim( $excerpt_raw );
			$current_excerpt = trim( (string) $post->post_excerpt );
			if ( $file_excerpt !== $current_excerpt ) {
				$result = wp_update_post(
					array(
						'ID' => $post_id,
						'post_excerpt' => wp_slash( $file_excerpt ),
					),
					true
				);
				if ( ! is_wp_error( $result ) ) {
					$changed = true;
					$messages[] = sprintf(
						/* translators: %s: relative plugin path */
						__( 'Imported excerpt for %s.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
					);
				} else {
					$messages[] = sprintf(
						/* translators: 1: relative path, 2: error message */
						__( 'Failed to import excerpt for %1$s: %2$s', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path,
						$result->get_error_message()
					);
				}
			}
		}

		return $changed;
	}

	/**
	 * Describe companion file drift (excerpt, meta description) for detect-differences.
	 * Returns an empty array for partials or when companion files are absent.
	 *
	 * @param string $type_key Content type key.
	 * @param WP_Post $post Post.
	 * @param string $html_filename HTML basename.
	 *
	 * @return list<string>
	 */
	public static function describe_companion_file_drift_for_detect(
		string $type_key,
		WP_Post $post,
		string $html_filename
	): array {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return array();
		}

		$issues = array();

		$excerpt_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_EXCERPTS, $html_filename );
		if ( null !== $excerpt_raw ) {
			$file_excerpt = trim( $excerpt_raw );
			$post_excerpt = trim( (string) $post->post_excerpt );
			if ( $file_excerpt !== $post_excerpt ) {
				$issues[] = __( 'Excerpt file differs from WordPress post excerpt.', \ASC_AI_PLUGIN_DOMAIN );
			}
		}

		return $issues;
	}

}
