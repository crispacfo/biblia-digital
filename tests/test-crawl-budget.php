<?php
/**
 * Tests for the crawl budget measures: verse anchors and conditional caching.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Crawl_Budget extends WP_UnitTestCase {

	private function plugin() {
		return BDWP70_Plugin::instance();
	}

	public function tear_down() {
		remove_all_filters( 'bdwp70_verse_anchor_links' );
		remove_all_filters( 'bdwp70_bible_last_modified' );
		delete_option( 'bdwp70_sitemap_lastmod' );
		parent::tear_down();
	}

	public function test_verse_numbers_point_to_a_local_anchor_by_default() {
		$this->assertTrue( $this->plugin()->verse_links_as_anchors() );
	}

	public function test_the_old_behaviour_can_be_restored_by_filter() {
		add_filter( 'bdwp70_verse_anchor_links', '__return_false' );
		$this->assertFalse( $this->plugin()->verse_links_as_anchors() );
	}

	public function test_last_modified_comes_from_the_content_date() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$this->assertSame(
			strtotime( '2026-03-04 00:00:00 UTC' ),
			$this->plugin()->bible_last_modified()
		);
	}

	public function test_last_modified_is_never_empty() {
		delete_option( 'bdwp70_sitemap_lastmod' );
		$this->assertGreaterThan( 0, $this->plugin()->bible_last_modified() );
	}

	public function test_etag_is_a_quoted_hash_and_is_stable() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$etag = $this->plugin()->bible_cache_etag();

		$this->assertMatchesRegularExpression( '/^"[a-f0-9]{32}"$/', $etag );
		$this->assertSame( $etag, $this->plugin()->bible_cache_etag(), 'O mesmo estado precisa gerar o mesmo ETag.' );
	}

	public function test_etag_changes_when_the_content_date_changes() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$antes = $this->plugin()->bible_cache_etag();

		update_option( 'bdwp70_sitemap_lastmod', '2026-03-05' );
		$this->assertNotSame( $antes, $this->plugin()->bible_cache_etag() );
	}

	public function test_etag_changes_when_the_displayed_title_changes() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$antes = $this->plugin()->bible_cache_etag();

		update_option( BDWP70_Plugin::OPTION_TITLE, 'Outro título' );
		$depois = $this->plugin()->bible_cache_etag();
		delete_option( BDWP70_Plugin::OPTION_TITLE );

		$this->assertNotSame( $antes, $depois, 'Mudar uma configuração precisa invalidar o ETag na hora.' );
	}

	public function test_etag_changes_when_the_markup_mode_changes() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$antes = $this->plugin()->bible_cache_etag();

		add_filter( 'bdwp70_verse_anchor_links', '__return_false' );
		$this->assertNotSame( $antes, $this->plugin()->bible_cache_etag() );
	}
}
