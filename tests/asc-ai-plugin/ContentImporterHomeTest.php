<?php

declare( strict_types = 1 );

use ASC\AI_BOILER\Admin\ContentExporter;
use ASC\AI_BOILER\Admin\ContentImporter;
use ASC\AI_BOILER\Admin\ContentManifest;
use ASC\AI_BOILER\Admin\ContentSync;
use ASC\AI_BOILER\Admin\ContentSyncProfile;
use ASC\AI_BOILER\Admin\SyncConfig;

final class ContentImporterHomeTest extends WP_UnitTestCase {

	private string $content_dir;
	private $content_dir_filter;

	public function set_up(): void {
		parent::set_up();

		$this->content_dir = sys_get_temp_dir() . '/asc-ai-boiler-home-' . wp_generate_uuid4() . '/';
		wp_mkdir_p( $this->content_dir . 'pages/' );
		file_put_contents( $this->content_dir . 'pages/home.html', '<h1>Imported Home</h1>' );
		file_put_contents(
			$this->content_dir . 'content-manifest.json',
			wp_json_encode(
				array(
					'types' => array(
						'pages' => array(
							array(
								'post_type' => 'page',
								'title' => 'Home',
								'slug' => 'home',
								'filename' => 'home.html',
							),
						),
					),
				)
			)
		);
		$this->content_dir_filter = function (): string {
			return $this->content_dir;
		};
		add_filter( 'asc_ai_boiler_content_dir', $this->content_dir_filter );
		update_option( SyncConfig::OPTION_SYNC_PAGES, '1' );
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );
		ContentManifest::invalidate_content_manifest_cache();
		ContentSyncProfile::invalidate_cache();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		remove_filter( 'asc_ai_boiler_content_dir', $this->content_dir_filter );
		ContentManifest::invalidate_content_manifest_cache();
		ContentSyncProfile::invalidate_cache();
		unlink( $this->content_dir . 'pages/home.html' );
		unlink( $this->content_dir . 'content-manifest.json' );
		rmdir( $this->content_dir . 'pages/' );
		rmdir( $this->content_dir );
		parent::tear_down();
	}

	public function test_import_creates_home_and_keeps_it_when_latest_posts_was_selected(): void {
		$messages = array();

		self::assertTrue( ContentImporter::import_one_file( 'pages', 'home.html', $messages ) );
		$home_id = (int) get_option( 'page_on_front' );
		self::assertGreaterThan( 0, $home_id );
		self::assertSame( 'page', get_option( 'show_on_front' ) );
		self::assertSame( 'publish', get_post_status( $home_id ) );
		self::assertSame( 'home', get_post_field( 'post_name', $home_id ) );
		self::assertSame( '<h1>Imported Home</h1>', get_post_field( 'post_content', $home_id ) );
		self::assertFalse( $this->is_orphan( $home_id ) );
		ContentImporter::delete_orphan_wordpress_posts_for_import( $messages );
		self::assertSame( 'publish', get_post_status( $home_id ) );
	}

	public function test_import_restores_a_home_page_from_the_trash(): void {
		$home_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => 'Home',
				'post_name' => 'home',
			)
		);
		wp_trash_post( $home_id );
		$messages = array();

		self::assertTrue( ContentImporter::import_one_file( 'pages', 'home.html', $messages ) );
		self::assertSame( $home_id, (int) get_option( 'page_on_front' ) );
		self::assertSame( 'publish', get_post_status( $home_id ) );
		self::assertSame( 'home', get_post_field( 'post_name', $home_id ) );
		self::assertFalse( $this->is_orphan( $home_id ) );
		ContentImporter::delete_orphan_wordpress_posts_for_import( $messages );
		self::assertSame( 'publish', get_post_status( $home_id ) );
	}

	public function test_latest_posts_ignores_a_stale_front_page_id(): void {
		$unrelated_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => 'Pool Cleaning, Repairs, and Renovations',
				'post_name' => 'pool-cleaning-repairs-and-renovations',
				'post_content' => '<p>Keep this content.</p>',
			)
		);
		update_option( 'page_on_front', $unrelated_id );
		$messages = array();
		self::assertSame(
			'pool-cleaning-repairs-and-renovations.html',
			ContentSync::derive_filename_for_post( 'pages', get_post( $unrelated_id ) )
		);

		self::assertTrue( ContentImporter::import_one_file( 'pages', 'home.html', $messages ) );
		$home_id = (int) get_option( 'page_on_front' );
		self::assertNotSame( $unrelated_id, $home_id );
		self::assertSame( 'page', get_option( 'show_on_front' ) );
		self::assertSame( 'publish', get_post_status( $home_id ) );
		self::assertSame( 'home', get_post_field( 'post_name', $home_id ) );
		self::assertSame( '<h1>Imported Home</h1>', get_post_field( 'post_content', $home_id ) );
		self::assertSame( '<p>Keep this content.</p>', get_post_field( 'post_content', $unrelated_id ) );
		self::assertContains( 'Set content/pages/home.html as the static front page (ID ' . $home_id . ').', $messages );
	}

	public function test_home_file_sets_static_front_page_without_a_manifest_row(): void {
		file_put_contents(
			$this->content_dir . 'content-manifest.json',
			wp_json_encode( array( 'types' => array( 'pages' => array() ) ) )
		);
		ContentManifest::invalidate_content_manifest_cache();
		$messages = array();

		self::assertTrue( ContentImporter::import_one_file( 'pages', 'home.html', $messages ) );
		$home_id = (int) get_option( 'page_on_front' );
		self::assertGreaterThan( 0, $home_id );
		self::assertSame( 'page', get_option( 'show_on_front' ) );
		self::assertSame( 'publish', get_post_status( $home_id ) );
		self::assertSame( 'home', get_post_field( 'post_name', $home_id ) );
		self::assertContains( 'Set content/pages/home.html as the static front page (ID ' . $home_id . ').', $messages );
	}

	private function is_orphan( int $post_id ): bool {
		foreach ( ContentExporter::collect_orphan_wordpress_posts() as $row ) {
			if ( $post_id === (int) $row['post_id'] ) {
				return true;
			}
		}
		return false;
	}
}
