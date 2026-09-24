<?php

declare( strict_types = 1 );

use ASC\AI_BOILER\Admin\ContentImporter;
use ASC\AI_BOILER\Admin\ContentManifest;
use ASC\AI_BOILER\Admin\SeoSync;
use ASC\AI_BOILER\Admin\SyncConfig;

final class SeoSyncTest extends WP_UnitTestCase {

	private string $content_dir;

	private $content_dir_filter;

	public function set_up(): void {
		parent::set_up();

		$this->content_dir = sys_get_temp_dir() . '/asc-ai-boiler-seo-' . wp_generate_uuid4() . '/';
		wp_mkdir_p( $this->content_dir );
		$this->content_dir_filter = function (): string {
			return $this->content_dir;
		};
		add_filter( 'asc_ai_boiler_content_dir', $this->content_dir_filter );
		update_option( SyncConfig::OPTION_YOAST_SYNC, '1' );
		register_post_type( 'seo_case', array( 'public' => true ) );
		ContentManifest::invalidate_content_manifest_cache();
	}

	public function tear_down(): void {
		remove_filter( 'asc_ai_boiler_content_dir', $this->content_dir_filter );
		unregister_post_type( 'seo_case' );
		$this->remove_directory( $this->content_dir );
		parent::tear_down();
	}

	public function test_imports_and_exports_every_supported_field(): void {
		$post_id = self::factory()->post->create();
		$term = wp_insert_term( 'Search', 'category', array( 'slug' => 'search' ) );
		wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), 'category' );
		$this->write_file( 'meta-descriptions/meta.txt', 'Meta value' );
		$this->write_file( 'social-descriptions/social.txt', 'Social value' );
		$this->write_file( 'x-descriptions/x.txt', 'X value' );
		$entry = array(
			'meta_description' => 'meta.txt',
			'social_description' => 'social.txt',
			'x_description' => 'x.txt',
			'social_title' => 'Social title',
			'x_title' => 'X title',
			'focus_keyphrase' => 'focus phrase',
			'primary_category' => 'search',
		);
		$messages = array();

		self::assertTrue( SeoSync::import_manifest_fields( $post_id, $entry, 'content/posts/test.html', $messages ) );
		self::assertTrue( SeoSync::import_primary_category( $post_id, 'post', $entry, 'content/posts/test.html', $messages ) );
		self::assertSame( 'Meta value', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
		self::assertSame( 'Social value', get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ) );
		self::assertSame( 'X value', get_post_meta( $post_id, '_yoast_wpseo_twitter-description', true ) );
		self::assertSame( 'Social title', get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ) );
		self::assertSame( 'X title', get_post_meta( $post_id, '_yoast_wpseo_twitter-title', true ) );
		self::assertSame( 'focus phrase', get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		self::assertSame( (string) $term['term_id'], get_post_meta( $post_id, '_yoast_wpseo_primary_category', true ) );

		$row = array();
		SeoSync::export_companion_files_for_post( get_post( $post_id ), 'test.html' );
		SeoSync::append_export_manifest_fields( get_post( $post_id ), 'test.html', $row );
		self::assertSame( 'test.txt', $row['meta_description'] );
		self::assertSame( 'test.txt', $row['social_description'] );
		self::assertSame( 'test.txt', $row['x_description'] );
		self::assertSame( 'Social title', $row['social_title'] );
		self::assertSame( 'X title', $row['x_title'] );
		self::assertSame( 'focus phrase', $row['focus_keyphrase'] );
		self::assertSame( 'search', $row['primary_category'] );
		self::assertSame( 'Meta value', file_get_contents( $this->content_dir . 'meta-descriptions/test.txt' ) );
		self::assertSame( 'Social value', file_get_contents( $this->content_dir . 'social-descriptions/test.txt' ) );
		self::assertSame( 'X value', file_get_contents( $this->content_dir . 'x-descriptions/test.txt' ) );
	}

	public function test_detects_differences_for_declared_fields_only(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', 'Database title' );

		$issues = SeoSync::describe_manifest_drift( get_post( $post_id ), array( 'social_title' => 'Manifest title' ) );
		self::assertCount( 1, $issues );
		self::assertSame( array(), SeoSync::describe_manifest_drift( get_post( $post_id ), array() ) );
	}

	public function test_missing_fields_and_files_preserve_existing_values(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', 'Keep meta' );
		update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', 'Keep title' );
		$messages = array();

		self::assertFalse( SeoSync::import_manifest_fields( $post_id, array(), 'content/posts/test.html', $messages ) );
		self::assertFalse( SeoSync::import_manifest_fields( $post_id, array( 'meta_description' => 'missing.txt' ), 'content/posts/test.html', $messages ) );
		self::assertSame( 'Keep meta', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
		self::assertSame( 'Keep title', get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ) );
	}

	public function test_empty_declared_values_clear_existing_values(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', 'Old meta' );
		update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', 'Old title' );
		update_post_meta( $post_id, '_yoast_wpseo_primary_category', '123' );
		$this->write_file( 'meta-descriptions/empty.txt', '' );
		$messages = array();

		self::assertTrue(
			SeoSync::import_manifest_fields(
				$post_id,
				array( 'meta_description' => 'empty.txt', 'social_title' => '' ),
				'content/posts/test.html',
				$messages
			)
		);
		self::assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
		self::assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ) );
		self::assertTrue( SeoSync::import_primary_category( $post_id, 'post', array( 'primary_category' => '' ), 'content/posts/test.html', $messages ) );
		self::assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_primary_category', true ) );
	}

	public function test_primary_category_requires_an_assigned_category(): void {
		$post_id = self::factory()->post->create();
		$term = wp_insert_term( 'Search', 'category', array( 'slug' => 'search' ) );
		update_post_meta( $post_id, '_yoast_wpseo_primary_category', '77' );
		$messages = array();

		self::assertFalse( SeoSync::import_primary_category( $post_id, 'post', array( 'primary_category' => 'search' ), 'content/posts/test.html', $messages ) );
		self::assertSame( '77', get_post_meta( $post_id, '_yoast_wpseo_primary_category', true ) );
		self::assertNotEmpty( $term );
	}

	/**
	 * @dataProvider postTypeProvider
	 */
	public function test_syncs_pages_posts_and_registered_custom_post_types( string $post_type ): void {
		$post_id = self::factory()->post->create( array( 'post_type' => $post_type ) );
		$messages = array();

		self::assertTrue(
			SeoSync::import_manifest_fields(
				$post_id,
				array( 'focus_keyphrase' => $post_type . ' phrase' ),
				'content/test.html',
				$messages
			)
		);
		self::assertSame( $post_type . ' phrase', get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
	}

	public static function postTypeProvider(): array {
		return array(
			'page' => array( 'page' ),
			'post' => array( 'post' ),
			'custom post type' => array( 'seo_case' ),
		);
	}

	public function test_uses_wordpress_metadata_when_yoast_is_inactive(): void {
		self::assertFalse( class_exists( 'WPSEO_Meta' ) );
		$post_id = self::factory()->post->create();
		$messages = array();

		self::assertTrue( SeoSync::import_manifest_fields( $post_id, array( 'x_title' => 'Inactive Yoast' ), 'content/posts/test.html', $messages ) );
		self::assertSame( 'Inactive Yoast', get_post_meta( $post_id, '_yoast_wpseo_twitter-title', true ) );
	}

	public function test_import_persists_metadata_before_post_update_hooks_run(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'order',
				'post_title' => 'Order',
				'post_content' => 'Old body',
				'post_status' => 'publish',
			)
		);
		$this->write_file( 'pages/order.html', 'New body' );
		$this->write_file( 'meta-descriptions/order.txt', 'Ready before hook' );
		$this->write_file(
			'content-manifest.json',
			wp_json_encode(
				array(
					'types' => array(
						'pages' => array(
							array(
								'post_type' => 'page',
								'title' => 'Order',
								'slug' => 'order',
								'filename' => 'order.html',
								'meta_description' => 'order.txt',
								'categories' => array(
									array( 'slug' => 'ordered', 'name' => 'Ordered' ),
								),
								'primary_category' => 'ordered',
							),
						),
					),
				)
			)
		);
		ContentManifest::invalidate_content_manifest_cache();
		$hook_values = array();
		$callback = static function ( int $updated_post_id ) use ( $post_id, &$hook_values ): void {
			if ( $updated_post_id === $post_id ) {
				$hook_values[] = array(
					'meta_description' => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
					'primary_category' => get_post_meta( $post_id, '_yoast_wpseo_primary_category', true ),
				);
			}
		};
		add_action( 'post_updated', $callback );
		$messages = array();

		ContentImporter::import_one_file( 'pages', 'order.html', $messages );
		remove_action( 'post_updated', $callback );

		self::assertNotEmpty( $hook_values );
		$last_hook_value = end( $hook_values );
		$primary_term = get_term_by( 'slug', 'ordered', 'category' );
		self::assertSame( 'Ready before hook', $last_hook_value['meta_description'] );
		self::assertSame( (string) $primary_term->term_id, $last_hook_value['primary_category'] );
	}

	private function write_file( string $relative_path, string $contents ): void {
		$path = $this->content_dir . $relative_path;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents );
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $directory );
	}

}
