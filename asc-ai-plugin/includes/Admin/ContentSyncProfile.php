<?php
/**
 * Manifest- and filter-driven metadata for {@see ContentSync} (no product-layer imports).
 *
 * Defaults are built from `content-manifest.json` plus WordPress conventions (for example
 * `home.html` resolves via `front_page`, and the front page always backs up as `home.html`).
 * Product code may adjust maps by filtering {@see ContentSyncProfile::FILTER_PROFILE}; partial
 * overrides merge on top of manifest-derived defaults.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_BOILER\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ASC\AI_BOILER\Core\RegisterPartials;

/**
 * Static sync profile: type list, partial shell filename map, and page seed resolvers.
 */
final class ContentSyncProfile {

	/**
	 * Filter: merge or replace fragments of the sync profile after manifest defaults are built.
	 *
	 * Callback receives:
	 * `array{
	 *   sync_types: array<string, array{post_type:string, label:string}>,
	 *   cpt_shell_map: array<string, string>,
	 *   page_body_map: array<string, array{type:string, slug?:string, title:string}>
	 * }`
	 *
	 * Return the same shape. Omitted sub-keys are left at defaults. Per-key merges use PHP
	 * `array_merge()` (later keys win for duplicates).
	 */
	public const FILTER_PROFILE = 'asc_ai_boiler_content_sync_profile';

	/**
	 * @var array{
	 *   sync_types: array<string, array{post_type:string, label:string}>,
	 *   cpt_shell_map: array<string, string>,
	 *   page_body_map: array<string, array{type:string, slug?:string, title:string}>
	 * }|null
	 */
	private static $cached = null;

	/**
	 * Drop cached profile (call when content-manifest.json changes on disk).
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {
		self::$cached = null;
	}

	/**
	 * Per-type config: content type key => { post_type, label } (filtered by active sync settings).
	 *
	 * @return array<string, array{post_type:string, label:string}>
	 */
	public static function sync_types(): array {
		$all = self::all_sync_types();
		$filtered = array();
		foreach ( $all as $key => $config ) {
			if ( SyncConfig::is_content_type_enabled( $key ) ) {
				$filtered[ $key ] = $config;
			}
		}
		return $filtered;
	}

	/**
	 * All registered content types (unfiltered).
	 *
	 * @return array<string, array{post_type:string, label:string}>
	 */
	public static function all_sync_types(): array {
		return self::resolved()['sync_types'];
	}

	/**
	 * Partials seed map: filename => partial key (matches {@see RegisterPartials::META_PARTIAL_KEY}).
	 *
	 * @return array<string, string>
	 */
	public static function cpt_shell_map(): array {
		return self::resolved()['cpt_shell_map'];
	}

	/**
	 * Pages map: filename => { type, slug?, title }.
	 *
	 * @return array<string, array{type:string, slug?:string, title:string}>
	 */
	public static function page_body_map(): array {
		return self::resolved()['page_body_map'];
	}

	/**
	 * Public list of enabled content types: { key, label }.
	 *
	 * @return list<array{key:string, label:string}>
	 */
	public static function type_list(): array {
		$out = array();
		foreach ( self::sync_types() as $key => $config ) {
			$out[] = array(
				'key' => $key,
				'label' => $config['label'],
			);
		}

		return $out;
	}

	/**
	 * Public list of all registered content types (including disabled ones): { key, label }.
	 *
	 * @return list<array{key:string, label:string}>
	 */
	public static function all_type_list(): array {
		$out = array();
		foreach ( self::all_sync_types() as $key => $config ) {
			$out[] = array(
				'key' => $key,
				'label' => $config['label'],
			);
		}

		return $out;
	}

	/**
	 * @return array{
	 *   sync_types: array<string, array{post_type:string, label:string}>,
	 *   cpt_shell_map: array<string, string>,
	 *   page_body_map: array<string, array{type:string, slug?:string, title:string}>
	 * }
	 */
	private static function resolved(): array {
		if ( null !== self::$cached ) {
			return self::$cached;
		}

		$defaults = self::build_manifest_driven_defaults();
		$over = apply_filters( self::FILTER_PROFILE, $defaults );
		if ( ! is_array( $over ) ) {
			self::$cached = $defaults;
			return self::$cached;
		}

		$over_sync = array();
		if ( isset( $over['sync_types'] ) && is_array( $over['sync_types'] ) ) {
			$over_sync = $over['sync_types'];
		}

		$over_shells = array();
		if ( isset( $over['cpt_shell_map'] ) && is_array( $over['cpt_shell_map'] ) ) {
			$over_shells = $over['cpt_shell_map'];
		}

		$over_pages = array();
		if ( isset( $over['page_body_map'] ) && is_array( $over['page_body_map'] ) ) {
			$over_pages = $over['page_body_map'];
		}

		self::$cached = array(
			'sync_types' => array_merge( $defaults['sync_types'], $over_sync ),
			'cpt_shell_map' => array_merge( $defaults['cpt_shell_map'], $over_shells ),
			'page_body_map' => array_merge( $defaults['page_body_map'], $over_pages ),
		);

		return self::$cached;
	}

	/**
	 * @return array{
	 *   sync_types: array<string, array{post_type:string, label:string}>,
	 *   cpt_shell_map: array<string, string>,
	 *   page_body_map: array<string, array{type:string, slug?:string, title:string}>
	 * }
	 */
	private static function build_manifest_driven_defaults(): array {
		$manifest_types = ContentSync::get_manifest_types_snapshot();

		$sync_types = array();
		foreach ( ContentSync::get_content_type_keys() as $type_key ) {
			$post_type = self::infer_post_type_for_type_key( $type_key, $manifest_types );
			if ( '' === $post_type ) {
				continue;
			}

			$sync_types[ $type_key ] = array(
				'post_type' => $post_type,
				'label' => self::label_for_type_key( $type_key ),
			);
		}

		return array(
			'sync_types' => $sync_types,
			'cpt_shell_map' => self::build_cpt_shell_map_from_manifest( $manifest_types ),
			'page_body_map' => self::build_page_body_map_from_manifest( $manifest_types ),
		);
	}

	/**
	 * @param array<string, array<int, array<string, mixed>>> $manifest_types
	 */
	private static function infer_post_type_for_type_key( string $type_key, array $manifest_types ): string {
		if ( SyncConfig::CONTENT_TYPE_PARTIALS === $type_key ) {
			return RegisterPartials::POST_TYPE;
		}

		if ( isset( $manifest_types[ $type_key ] ) && is_array( $manifest_types[ $type_key ] ) ) {
			foreach ( $manifest_types[ $type_key ] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( ! isset( $row['post_type'] ) ) {
					continue;
				}
				$pt = trim( (string) $row['post_type'] );
				if ( '' !== $pt ) {
					return $pt;
				}
			}
		}

		switch ( $type_key ) {
			case SyncConfig::CONTENT_TYPE_PARTIALS:
				return RegisterPartials::POST_TYPE;
			case SyncConfig::CONTENT_TYPE_PAGES:
				return 'page';
			case SyncConfig::CONTENT_TYPE_POSTS:
				return 'post';
			default:
				return '';
		}
	}

	private static function label_for_type_key( string $type_key ): string {
		switch ( $type_key ) {
			case SyncConfig::CONTENT_TYPE_PARTIALS:
				return __( 'Partials', \ASC_AI_PLUGIN_DOMAIN );
			case SyncConfig::CONTENT_TYPE_PAGES:
				return __( 'Pages', \ASC_AI_PLUGIN_DOMAIN );
			case SyncConfig::CONTENT_TYPE_POSTS:
				return __( 'Posts', \ASC_AI_PLUGIN_DOMAIN );
			default:
				break;
		}

		$readable = $type_key;
		$readable = str_replace( array( '-', '_' ), ' ', $readable );
		$readable = trim( $readable );
		if ( '' === $readable ) {
			return $type_key;
		}

		return ucwords( $readable );
	}

	/**
	 * @param array<string, array<int, array<string, mixed>>> $manifest_types
	 *
	 * @return array<string, string>
	 */
	private static function build_cpt_shell_map_from_manifest( array $manifest_types ): array {
		$map = array();
		if ( ! isset( $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ] ) ) {
			return $map;
		}

		$rows = $manifest_types[ SyncConfig::CONTENT_TYPE_PARTIALS ];
		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$fn = ContentSync::manifest_resolve_filename_from_entry( $entry );
			if ( '' === $fn ) {
				continue;
			}

			$partial_key = '';
			if ( isset( $entry['partial_key'] ) ) {
				$partial_key = trim( (string) $entry['partial_key'] );
			}

			if ( '' === $partial_key && isset( $entry['slug'] ) ) {
				$partial_key = str_replace( '-', '_', trim( (string) $entry['slug'] ) );
			}

			if ( '' === $partial_key ) {
				$slug_from_file = self::filename_to_slug( $fn );
				if ( '' !== $slug_from_file ) {
					$partial_key = str_replace( '-', '_', $slug_from_file );
				}
			}

			$partial_key = trim( $partial_key );
			if ( '' !== $partial_key ) {
				$map[ $fn ] = $partial_key;
			}
		}

		return $map;
	}

	/**
	 * @param array<string, array<int, array<string, mixed>>> $manifest_types
	 *
	 * @return array<string, array{type:string, slug?:string, title:string}>
	 */
	private static function build_page_body_map_from_manifest( array $manifest_types ): array {
		$map = array();
		if ( ! isset( $manifest_types[ SyncConfig::CONTENT_TYPE_PAGES ] ) ) {
			return $map;
		}

		$rows = $manifest_types[ SyncConfig::CONTENT_TYPE_PAGES ];
		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$fn = ContentSync::manifest_resolve_filename_from_entry( $entry );
			if ( '' === $fn ) {
				continue;
			}

			$title = self::manifest_entry_title( $entry, $fn );

			if ( 'home.html' === $fn ) {
				$map[ $fn ] = array(
					'type' => 'front_page',
					'title' => $title,
				);
				continue;
			}

			$slug = '';
			if ( isset( $entry['slug'] ) ) {
				$slug = trim( (string) $entry['slug'] );
			}

			if ( '' === $slug ) {
				$slug = self::filename_to_slug( $fn );
			}

			$map[ $fn ] = array(
				'type' => 'slug',
				'slug' => $slug,
				'title' => $title,
			);
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $entry Manifest row.
	 * @param string $filename Resolved HTML basename.
	 */
	private static function manifest_entry_title( array $entry, string $filename ): string {
		if ( isset( $entry['title'] ) ) {
			$t = trim( (string) $entry['title'] );
			if ( '' !== $t ) {
				return $t;
			}
		}

		return self::title_fallback_from_slug( self::filename_to_slug( $filename ) );
	}

	private static function filename_to_slug( string $filename ): string {
		if ( '.html' !== substr( $filename, -5 ) ) {
			return '';
		}

		return substr( $filename, 0, -5 );
	}

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
}
