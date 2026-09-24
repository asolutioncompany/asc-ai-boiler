<?php

declare( strict_types = 1 );

use ASC\AI_BOILER\Admin\ContentManifest;
use ASC\AI_BOILER\Admin\SyncConfig;
use ASC\AI_BOILER\Admin\TaxonomySync;

final class TaxonomySyncTest extends WP_UnitTestCase {

	private string $content_dir;

	private $content_dir_filter;

	public function set_up(): void {
		parent::set_up();

		$this->content_dir = sys_get_temp_dir() . '/asc-ai-boiler-taxonomy-' . wp_generate_uuid4() . '/';
		wp_mkdir_p( $this->content_dir );
		$this->content_dir_filter = function (): string {
			return $this->content_dir;
		};
		add_filter( 'asc_ai_boiler_content_dir', $this->content_dir_filter );
		update_option( SyncConfig::OPTION_SYNC_POSTS, '1' );
		ContentManifest::invalidate_content_manifest_cache();
	}

	public function tear_down(): void {
		remove_filter( 'asc_ai_boiler_content_dir', $this->content_dir_filter );
		delete_option( SyncConfig::OPTION_SYNC_POSTS );
		$this->remove_directory( $this->content_dir );
		parent::tear_down();
	}

	public function test_imports_declared_taxonomy_names_and_descriptions(): void {
		$this->write_manifest(
			array(
				'category' => array(
					array(
						'slug' => 'small-business',
						'name' => 'Small Business',
						'description' => 'Local business description.',
					),
				),
			)
		);
		$messages = array();

		self::assertTrue( TaxonomySync::import_manifest_taxonomies( $messages ) );
		$term = get_term_by( 'slug', 'small-business', 'category' );
		self::assertInstanceOf( WP_Term::class, $term );
		self::assertSame( 'Small Business', $term->name );
		self::assertSame( 'Local business description.', $term->description );
		self::assertSame( array(), TaxonomySync::describe_manifest_drift() );
	}

	public function test_missing_description_preserves_existing_value_and_empty_description_clears_it(): void {
		$created = wp_insert_term(
			'Blog',
			'post_tag',
			array(
				'slug' => 'blog',
				'description' => 'Keep this description.',
			)
		);
		self::assertIsArray( $created );
		$messages = array();

		$this->write_manifest( array( 'post_tag' => array( array( 'slug' => 'blog', 'name' => 'Blog' ) ) ) );
		self::assertFalse( TaxonomySync::import_manifest_taxonomies( $messages ) );
		$term = get_term_by( 'slug', 'blog', 'post_tag' );
		self::assertSame( 'Keep this description.', $term->description );

		$this->write_manifest( array( 'post_tag' => array( array( 'slug' => 'blog', 'description' => '' ) ) ) );
		self::assertTrue( TaxonomySync::import_manifest_taxonomies( $messages ) );
		$term = get_term_by( 'slug', 'blog', 'post_tag' );
		self::assertSame( '', $term->description );
	}

	public function test_detects_declared_description_drift(): void {
		wp_insert_term( 'Blog', 'post_tag', array( 'slug' => 'blog', 'description' => 'WordPress copy.' ) );
		$this->write_manifest( array( 'post_tag' => array( array( 'slug' => 'blog', 'description' => 'Manifest copy.' ) ) ) );

		self::assertCount( 1, TaxonomySync::describe_manifest_drift() );
		self::assertCount( 1, TaxonomySync::detect_differences() );
	}

	public function test_exports_descriptions_for_terms_assigned_to_synced_content(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$created = wp_insert_term( 'Blog', 'post_tag', array( 'slug' => 'blog', 'description' => 'Exported copy.' ) );
		wp_set_object_terms( $post_id, array( (int) $created['term_id'] ), 'post_tag' );

		$taxonomies = TaxonomySync::build_export_manifest_taxonomies();
		self::assertSame( 'blog', $taxonomies['post_tag'][0]['slug'] );
		self::assertSame( 'Blog', $taxonomies['post_tag'][0]['name'] );
		self::assertSame( 'Exported copy.', $taxonomies['post_tag'][0]['description'] );
	}

	private function write_manifest( array $taxonomies ): void {
		file_put_contents(
			$this->content_dir . 'content-manifest.json',
			wp_json_encode( array( 'types' => array(), 'taxonomies' => $taxonomies ) )
		);
		ContentManifest::invalidate_content_manifest_cache();
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
