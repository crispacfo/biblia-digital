<?php
/**
 * Tests for the LiteSpeed Cache tag and purge scope of the Bible pages.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_LSCache_Tags extends WP_UnitTestCase {

	private $purges = array();

	public function set_up() {
		parent::set_up();
		$this->purges = array();
		add_action(
			'litespeed_purge',
			function ( $tags ) {
				$this->purges[] = array( 'purge', (array) $tags );
			}
		);
		add_action(
			'litespeed_purge_all',
			function () {
				$this->purges[] = array( 'purge_all', array() );
			}
		);
	}

	public function test_purge_is_limited_to_the_bible_tag() {
		BDWP70_Plugin::purge_bible_cache();
		$this->assertSame( array( array( 'purge', array( 'bdwp70_bible' ) ) ), $this->purges );
	}

	public function test_purge_can_target_a_single_bible_version() {
		BDWP70_Plugin::purge_bible_cache( 7 );
		$this->assertSame( array( array( 'purge', array( 'bdwp70_bible', 'bdwp70_bible_7' ) ) ), $this->purges );
	}

	public function test_filter_restores_the_previous_site_wide_purge() {
		add_filter( 'bdwp70_purge_all_caches', '__return_true' );
		BDWP70_Plugin::purge_bible_cache();
		remove_filter( 'bdwp70_purge_all_caches', '__return_true' );

		$this->assertSame( array( array( 'purge_all', array() ) ), $this->purges );
	}

	public function test_bible_page_adds_its_own_cache_tag() {
		$tags = array();
		add_action(
			'litespeed_tag_add',
			function ( $added ) use ( &$tags ) {
				$tags = array_merge( $tags, (array) $added );
			}
		);

		BDWP70_Plugin::instance()->lscache_tag_bible_page( 3 );

		$this->assertContains( 'bdwp70_bible', $tags );
		$this->assertContains( 'bdwp70_bible_3', $tags );
	}
}
