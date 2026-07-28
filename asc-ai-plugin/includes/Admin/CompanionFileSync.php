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

final class CompanionFileSync {

	/**
	 * Filter: override the active SEO meta description key.
	 */
	public const FILTER_META_DESCRIPTION_META_KEY = 'asc_ai_boiler_meta_description_meta_key';

	/**
	 * Default SEO meta description key.
	 */
	public const META_DESCRIPTION_META_KEY_DEFAULT = '_asc_ai_boiler_meta_description';

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
	 * Active SEO meta key for reading/writing meta descriptions. Filtered by {@see FILTER_META_DESCRIPTION_META_KEY}.
	 *
	 * @return string
	 */
	public static function get_active_meta_description_meta_key(): string {
		$default = self::META_DESCRIPTION_META_KEY_DEFAULT;
		if ( SyncConfig::is_yoast_sync() || defined( 'WPSEO_VERSION' ) ) {
			$default = '_yoast_wpseo_metadesc';
		}
		$key = (string) apply_filters( self::FILTER_META_DESCRIPTION_META_KEY, $default );
		$key = trim( $key );
		if ( '' === $key ) {
			return $default;
		}
		return $key;
	}

	/**
	 * Whether the active meta description key is a Yoast SEO key.
	 *
	 * @return bool
	 */
	public static function is_yoast_meta_description_active(): bool {
		$key = self::get_active_meta_description_meta_key();
		return 0 === strpos( $key, '_yoast' );
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
	 * Read the SEO meta description for a post via the active meta key.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function get_post_meta_description( int $post_id ): string {
		$meta_key = self::get_active_meta_description_meta_key();
		if ( 0 === strpos( $meta_key, '_yoast' ) ) {
			return self::get_post_meta_raw( $post_id, $meta_key );
		}
		return (string) get_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Write the SEO meta description for a post via the active meta key.
	 * Deletes the meta entry when `$value` is empty.
	 *
	 * @param int $post_id Post ID.
	 * @param string $value Meta description value.
	 *
	 * @return void
	 */
	public static function set_post_meta_description( int $post_id, string $value ): void {
		$meta_key = self::get_active_meta_description_meta_key();
		if ( 0 === strpos( $meta_key, '_yoast' ) ) {
			if ( '' === $value ) {
				global $wpdb;
				$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post_id, 'meta_key' => $meta_key ) );
			} else {
				self::update_post_meta_raw( $post_id, $meta_key, $value );
			}
			return;
		}

		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Write excerpt and meta description companion files for a post during export.
	 * Skipped for partials (which have no excerpt or SEO meta description).
	 *
	 * @param WP_Post $post Post to back up.
	 * @param string $html_filename HTML basename of the content file.
	 *
	 * @return void
	 */
	public static function export_companion_files_for_post( WP_Post $post, string $html_filename ): void {
		$excerpt = trim( (string) $post->post_excerpt );
		self::write_companion_text_file( SyncConfig::CONTENT_DIR_EXCERPTS, $html_filename, $excerpt );

		if ( ! SyncConfig::is_yoast_sync() && self::is_yoast_meta_description_active() ) {
			return;
		}

		$meta_description = trim( self::get_post_meta_description( (int) $post->ID ) );
		self::write_companion_text_file( SyncConfig::CONTENT_DIR_META_DESCRIPTIONS, $html_filename, $meta_description );

		if ( SyncConfig::is_yoast_sync() ) {
			$fb_desc = trim( self::get_post_meta_raw( (int) $post->ID, '_yoast_wpseo_opengraph-description' ) );
			self::write_companion_text_file( SyncConfig::CONTENT_DIR_SOCIAL_DESCRIPTIONS, $html_filename, $fb_desc );

			$tw_desc = trim( self::get_post_meta_raw( (int) $post->ID, '_yoast_wpseo_twitter-description' ) );
			self::write_companion_text_file( SyncConfig::CONTENT_DIR_X_DESCRIPTIONS, $html_filename, $tw_desc );
		}
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
		$title = trim( (string) $post->post_title );

		if ( null !== $manifest_entry && isset( $manifest_entry['focus_keyphrase'] ) && SyncConfig::is_yoast_sync() ) {
			$focus_keyphrase = trim( (string) $manifest_entry['focus_keyphrase'] );
			$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_focuskw' ) );
			if ( $focus_keyphrase !== $current ) {
				self::update_post_meta_raw( $post_id, '_yoast_wpseo_focuskw', $focus_keyphrase );
				$changed = true;
				$messages[] = sprintf(
					/* translators: %s: relative plugin path */
					__( 'Imported Yoast focus keyphrase for %s.', \ASC_AI_PLUGIN_DOMAIN ),
					$relative_path
				);
			}
		}

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

		$file_meta_desc = '';
		if ( SyncConfig::is_yoast_sync() || ! self::is_yoast_meta_description_active() ) {
			$meta_desc_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_META_DESCRIPTIONS, $html_filename );
			if ( null !== $meta_desc_raw ) {
				$file_meta_desc = trim( $meta_desc_raw );
				$current_meta_desc = trim( self::get_post_meta_description( $post_id ) );
				if ( $file_meta_desc !== $current_meta_desc ) {
					self::set_post_meta_description( $post_id, $file_meta_desc );
					$changed = true;
					$messages[] = sprintf(
						/* translators: %s: relative plugin path */
						__( 'Imported meta description for %s.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
					);
				}
			}
		}

		if ( SyncConfig::is_yoast_sync() ) {
			// Facebook Title
			$fb_title_raw = $manifest_entry['social_title'] ?? '';
			$fb_title = '';
			if ( null !== $fb_title_raw ) {
				$fb_title = trim( (string) $fb_title_raw );
			}
			if ( '' === $fb_title && '' !== $title ) {
				$site_name = get_bloginfo( 'name' );
				$fb_title = $title;
				if ( '' !== $site_name ) {
					$fb_title = $title . ' - ' . $site_name;
				}
			}
			if ( '' !== $fb_title ) {
				$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-title' ) );
				if ( $fb_title !== $current ) {
					self::update_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-title', $fb_title );
					$changed = true;
					$messages[] = sprintf(
						/* translators: %s: relative plugin path */
						__( 'Imported Yoast Facebook title for %s.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
					);
				}
			}

			// Twitter Title
			$tw_title_raw = $manifest_entry['x_title'] ?? '';
			$tw_title = '';
			if ( null !== $tw_title_raw ) {
				$tw_title = trim( (string) $tw_title_raw );
			}
			if ( '' === $tw_title && '' !== $title ) {
				$site_name = get_bloginfo( 'name' );
				$tw_title = $title;
				if ( '' !== $site_name ) {
					$tw_title = $title . ' - ' . $site_name;
				}
			}
			if ( '' !== $tw_title ) {
				$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_twitter-title' ) );
				if ( $tw_title !== $current ) {
					self::update_post_meta_raw( $post_id, '_yoast_wpseo_twitter-title', $tw_title );
					$changed = true;
					$messages[] = sprintf(
						/* translators: %s: relative plugin path */
						__( 'Imported Yoast Twitter title for %s.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
					);
				}
			}

			// Facebook Description
			$fb_desc_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_SOCIAL_DESCRIPTIONS, $html_filename );
			$fb_desc = '';
			if ( null !== $fb_desc_raw ) {
				$fb_desc = trim( $fb_desc_raw );
			}
			if ( '' === $fb_desc ) {
				$fb_desc = trim( self::get_post_meta_description( $post_id ) );
				if ( '' === $fb_desc && '' !== $file_meta_desc ) {
					$fb_desc = $file_meta_desc;
				}
			}
			if ( '' !== $fb_desc ) {
				$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-description' ) );
				if ( $fb_desc !== $current ) {
					self::update_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-description', $fb_desc );
					$changed = true;
					$messages[] = sprintf(
						/* translators: %s: relative plugin path */
						__( 'Imported Yoast Facebook description for %s.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
					);
				}
			}

			// Twitter Description
			$tw_desc_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_X_DESCRIPTIONS, $html_filename );
			$tw_desc = '';
			if ( null !== $tw_desc_raw ) {
				$tw_desc = trim( $tw_desc_raw );
			}
			if ( '' === $tw_desc ) {
				$tw_desc = trim( self::get_post_meta_description( $post_id ) );
				if ( '' === $tw_desc && '' !== $file_meta_desc ) {
					$tw_desc = $file_meta_desc;
				}
			}
			if ( '' !== $tw_desc ) {
				$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_twitter-description' ) );
				if ( $tw_desc !== $current ) {
					self::update_post_meta_raw( $post_id, '_yoast_wpseo_twitter-description', $tw_desc );
					$changed = true;
					$messages[] = sprintf(
						/* translators: %s: relative plugin path */
						__( 'Imported Yoast Twitter description for %s.', \ASC_AI_PLUGIN_DOMAIN ),
						$relative_path
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
		$post_id = (int) $post->ID;

		$excerpt_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_EXCERPTS, $html_filename );
		if ( null !== $excerpt_raw ) {
			$file_excerpt = trim( $excerpt_raw );
			$post_excerpt = trim( (string) $post->post_excerpt );
			if ( $file_excerpt !== $post_excerpt ) {
				$issues[] = __( 'Excerpt file differs from WordPress post excerpt.', \ASC_AI_PLUGIN_DOMAIN );
			}
		}

		if ( SyncConfig::is_yoast_sync() || ! self::is_yoast_meta_description_active() ) {
			$meta_desc_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_META_DESCRIPTIONS, $html_filename );
			if ( null !== $meta_desc_raw ) {
				$file_meta_desc = trim( $meta_desc_raw );
				$current_meta_desc = trim( self::get_post_meta_description( $post_id ) );
				if ( $file_meta_desc !== $current_meta_desc ) {
					$issues[] = __( 'Meta description file differs from WordPress meta description.', \ASC_AI_PLUGIN_DOMAIN );
				}
			}
		}

		if ( SyncConfig::is_yoast_sync() ) {
			$fb_desc_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_SOCIAL_DESCRIPTIONS, $html_filename );
			if ( null !== $fb_desc_raw ) {
				$file_fb_desc = trim( $fb_desc_raw );
				$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_opengraph-description' ) );
				if ( $file_fb_desc !== $current ) {
					$issues[] = __( 'Facebook description file differs from WordPress Facebook description.', \ASC_AI_PLUGIN_DOMAIN );
				}
			}

			$tw_desc_raw = self::read_companion_text_file( SyncConfig::CONTENT_DIR_X_DESCRIPTIONS, $html_filename );
			if ( null !== $tw_desc_raw ) {
				$file_tw_desc = trim( $tw_desc_raw );
				$current = trim( self::get_post_meta_raw( $post_id, '_yoast_wpseo_twitter-description' ) );
				if ( $file_tw_desc !== $current ) {
					$issues[] = __( 'Twitter description file differs from WordPress Twitter description.', \ASC_AI_PLUGIN_DOMAIN );
				}
			}
		}

		return $issues;
	}

}
