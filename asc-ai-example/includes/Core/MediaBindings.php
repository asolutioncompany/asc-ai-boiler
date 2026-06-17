<?php
/**
 * Example site media paths and manifest bindings for {@see ContentMediaSync}.
 *
 * @package asc-ai-boiler
 */

declare( strict_types = 1 );

namespace ASC\AI_EXAMPLE\Core;

use ASC\AI_BOILER\Core\ContentMediaSync;

/**
 * Maps example content to plugin media under content/media/.
 */
final class MediaBindings {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( ContentMediaSync::FILTER_MEDIA_BINDINGS, array( self::class, 'filter_media_bindings' ) );
		add_filter( ContentMediaSync::FILTER_SETTING_MEDIA_PATH, array( self::class, 'filter_setting_media_path' ), 10, 2 );
		add_filter( ContentMediaSync::FILTER_POST_MEDIA_PATH, array( self::class, 'filter_post_media_path' ), 10, 3 );
	}

	/**
	 * @param list<array<string, mixed>> $bindings Existing bindings.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function filter_media_bindings( array $bindings ): array {
		if ( array() !== $bindings ) {
			return $bindings;
		}

		return self::default_bindings();
	}

	/**
	 * @param string $path Current relative media path.
	 * @param string $setting_key Image settings key.
	 *
	 * @return string
	 */
	public static function filter_setting_media_path( string $path, string $setting_key ): string {
		$map = array(
			CoreSettings::SETTING_IMAGE_BLOG_DEFAULT => 'blog-default.jpg',
			CoreSettings::SETTING_IMAGE_SERVICES => 'services-default.jpg',
			CoreSettings::SETTING_IMAGE_PROJECTS => 'projects-default.jpg',
		);

		if ( isset( $map[ $setting_key ] ) ) {
			return $map[ $setting_key ];
		}

		return $path;
	}

	/**
	 * @param string $path Current relative media path.
	 * @param string $post_type Post type slug.
	 * @param string $slug Post slug.
	 *
	 * @return string
	 */
	public static function filter_post_media_path( string $path, string $post_type, string $slug ): string {
		$blog_map = array(
			'ai-assisted-wordpress-development-improves-seo' => 'blog-seo.jpg',
			'ai-assisted-wordpress-development-improves-design' => 'blog-design.jpg',
			'ai-assisted-wordpress-development-improves-performance-and-security' => 'blog-performance.jpg',
		);

		if ( 'post' === $post_type && isset( $blog_map[ $slug ] ) ) {
			return $blog_map[ $slug ];
		}

		$project_map = array(
			'pool-service-company' => 'project-pool-service.jpg',
			'landscaping-company' => 'project-landscaping.jpg',
			'dog-groomer' => 'project-dog-groomer.jpg',
		);

		if ( RegisterProjects::POST_TYPE === $post_type && isset( $project_map[ $slug ] ) ) {
			return $project_map[ $slug ];
		}

		$service_icon_map = array(
			'wordpress-design' => 'service-design.png',
			'hosting' => 'service-hosting.png',
			'maintenance' => 'service-maintenance.png',
		);

		if ( RegisterServices::POST_TYPE === $post_type && isset( $service_icon_map[ $slug ] ) ) {
			return $service_icon_map[ $slug ];
		}

		return $path;
	}

	/**
	 * Foreground dashicon class for a service slug.
	 *
	 * @param string $slug Service post slug.
	 *
	 * @return string Dashicon CSS class or empty string.
	 */
	public static function service_dashicon( string $slug ): string {
		$map = array(
			'wordpress-design' => 'dashicons-art',
			'hosting'          => 'dashicons-cloud',
			'maintenance'      => 'dashicons-admin-tools',
			'help'             => 'dashicons-sos',
		);

		return $map[ $slug ] ?? '';
	}

	/**
	 * Smaller list-view service icon under content/media/icons/.
	 *
	 * @param string $slug Service post slug.
	 *
	 * @return string Relative path or empty string.
	 */
	public static function service_list_icon_relative_path( string $slug ): string {
		$list_icon_map = array(
			'wordpress-design' => 'service-design.png',
			'hosting' => 'service-hosting.png',
			'maintenance' => 'service-maintenance.png',
		);

		if ( isset( $list_icon_map[ $slug ] ) ) {
			return $list_icon_map[ $slug ];
		}

		return '';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function default_bindings(): array {
		$option = CoreSettings::OPTION_KEY;

		return array(
			array(
				'media_filename' => 'blog-default.jpg',
				'target' => 'setting',
				'option' => $option,
				'key' => CoreSettings::SETTING_IMAGE_BLOG_DEFAULT,
			),
			array(
				'media_filename' => 'services-default.jpg',
				'target' => 'setting',
				'option' => $option,
				'key' => CoreSettings::SETTING_IMAGE_SERVICES,
			),
			array(
				'media_filename' => 'projects-default.jpg',
				'target' => 'setting',
				'option' => $option,
				'key' => CoreSettings::SETTING_IMAGE_PROJECTS,
			),
			array(
				'media_filename' => 'blog-seo.jpg',
				'target' => 'featured',
				'post_type' => 'post',
				'slug' => 'ai-assisted-wordpress-development-improves-seo',
			),
			array(
				'media_filename' => 'blog-design.jpg',
				'target' => 'featured',
				'post_type' => 'post',
				'slug' => 'ai-assisted-wordpress-development-improves-design',
			),
			array(
				'media_filename' => 'blog-performance.jpg',
				'target' => 'featured',
				'post_type' => 'post',
				'slug' => 'ai-assisted-wordpress-development-improves-performance-and-security',
			),
			array(
				'media_filename' => 'project-pool-service.jpg',
				'target' => 'featured',
				'post_type' => RegisterProjects::POST_TYPE,
				'slug' => 'pool-service-company',
			),
			array(
				'media_filename' => 'project-landscaping.jpg',
				'target' => 'featured',
				'post_type' => RegisterProjects::POST_TYPE,
				'slug' => 'landscaping-company',
			),
			array(
				'media_filename' => 'project-dog-groomer.jpg',
				'target' => 'featured',
				'post_type' => RegisterProjects::POST_TYPE,
				'slug' => 'dog-groomer',
			),
		);
	}
}
