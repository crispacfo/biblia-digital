<?php
/**
 * Rewrite/query-var registration and sitemap availability.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Rewrite_Sitemap extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		BDWP70_Activator::activate();
	}

	public function test_public_query_vars_are_registered() {
		$plugin = BDWP70_Plugin::instance();
		$vars   = $plugin->query_vars( array() );
		foreach ( array( 'bdwp_bible', 'bdwp_livro_slug', 'bdwp_capitulo', 'bdwp_versiculo', 'bdwp_versao_slug' ) as $expected ) {
			$this->assertContains( $expected, $vars, "Missing query var {$expected}" );
		}
	}

	public function test_register_rewrite_adds_bible_rules() {
		$plugin = BDWP70_Plugin::instance();
		$plugin->register_rewrite();
		$rules = get_option( 'rewrite_rules' );
		$plugin->register_rewrite();

		// The internal rewrite array is populated on WP's global object.
		global $wp_rewrite;
		$this->assertNotEmpty( $wp_rewrite->extra_rules_top, 'No top rewrite rules were registered.' );
		$joined = implode( ' ', array_values( $wp_rewrite->extra_rules_top ) );
		$this->assertStringContainsString( 'bdwp_bible=1', $joined );
	}

	public function test_sitemap_is_enabled_by_default() {
		$this->assertTrue( BDWP70_Sitemap::is_enabled() );
	}

	public function test_sitemap_provider_lists_urls_when_data_exists() {
		if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! class_exists( 'BDWP70_Sitemap_Provider' ) ) {
			$this->markTestSkipped( 'WP Sitemaps API or provider not available.' );
		}
		global $wpdb;
		$bible_id = BDWP70_Activator::create_bible_version( 'Sitemap', 'en_US', 'unit', 0 );
		update_option( BDWP70_Activator::OPTION_ACTIVE_BIBLE, $bible_id );
		$wpdb->insert(
			BDWP70_Activator::books_table(),
			array(
				'bible_id'   => $bible_id,
				'livro'      => 'John',
				'livro_desc' => 'John',
				'livro_seq'  => 43,
				'published'  => 1,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);
		$wpdb->insert(
			BDWP70_Activator::verses_table(),
			array(
				'bible_id'   => $bible_id,
				'testamento' => 'NT',
				'livroseq'   => 43,
				'livro'      => 'John',
				'capitulo'   => 3,
				'versiculo'  => 16,
				'palavra'    => 'x',
				'published'  => 1,
				'hits'       => 0,
			),
			array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%d' )
		);
		BDWP70_Activator::clear_runtime_caches();

		$provider = new BDWP70_Sitemap_Provider( BDWP70_Plugin::instance() );
		$urls     = $provider->get_url_list( 1, 'biblia-digital' );
		$this->assertIsArray( $urls );
		$this->assertNotEmpty( $urls );
	}
}
