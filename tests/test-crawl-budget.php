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
		delete_option( BDWP70_Plugin::OPTION_RENDER_EPOCH );
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

	public function test_bump_render_epoch_stamps_the_current_time() {
		delete_option( BDWP70_Plugin::OPTION_RENDER_EPOCH );
		$antes = time() - 1;

		BDWP70_Plugin::bump_render_epoch();

		$this->assertGreaterThan( $antes, (int) get_option( BDWP70_Plugin::OPTION_RENDER_EPOCH ) );
	}

	public function test_render_epoch_moves_the_last_modified_forward() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$conteudo = $this->plugin()->bible_last_modified();
		$depois   = $conteudo + DAY_IN_SECONDS;

		update_option( BDWP70_Plugin::OPTION_RENDER_EPOCH, $depois );

		$this->assertSame( $depois, $this->plugin()->bible_last_modified() );
	}

	public function test_an_older_render_epoch_never_moves_the_date_back() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$conteudo = $this->plugin()->bible_last_modified();

		update_option( BDWP70_Plugin::OPTION_RENDER_EPOCH, $conteudo - DAY_IN_SECONDS );

		$this->assertSame( $conteudo, $this->plugin()->bible_last_modified() );
	}

	public function test_etag_changes_when_the_theme_changes() {
		update_option( 'bdwp70_sitemap_lastmod', '2026-03-04' );
		$antes = $this->plugin()->bible_cache_etag();

		do_action( 'switch_theme', 'outro-tema' );

		$this->assertNotSame(
			$antes,
			$this->plugin()->bible_cache_etag(),
			'Trocar de tema muda o HTML da página e precisa invalidar a revalidação condicional.'
		);
	}

	public function test_every_appearance_change_is_hooked() {
		$ganchos = array(
			'switch_theme',
			'upgrader_process_complete',
			'customize_save_after',
			'wp_update_nav_menu',
			'update_option_sidebars_widgets',
			'activated_plugin',
			'deactivated_plugin',
		);

		foreach ( $ganchos as $gancho ) {
			$this->assertNotFalse(
				has_action( $gancho, array( 'BDWP70_Plugin', 'bump_render_epoch' ) ),
				$gancho . ' precisa carimbar a renderização.'
			);
		}
	}
}
