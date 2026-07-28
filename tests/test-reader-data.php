<?php
/**
 * Reader data access, including the 1.1.67 abuse-fix random selection
 * (id-range instead of COUNT/OFFSET), which must return a verse from the
 * seeded, published set only.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Reader_Data extends WP_UnitTestCase {

	private $bible_id;

	public function set_up() {
		parent::set_up();
		BDWP70_Activator::activate();
		$this->bible_id = BDWP70_Activator::create_bible_version( 'Reader', 'en_US', 'unit', 0 );
		update_option( BDWP70_Activator::OPTION_ACTIVE_BIBLE, $this->bible_id );
		$this->seed();
	}

	private function seed() {
		global $wpdb;
		$rows = array(
			array( 43, 'John', 3, 16, 'For God so loved the world.' ),
			array( 43, 'John', 3, 17, 'For God sent not his Son to condemn.' ),
			array( 19, 'Ps', 23, 1, 'The Lord is my shepherd.' ),
			array( 1, 'Gn', 1, 1, 'In the beginning God created.' ),
		);
		foreach ( $rows as $r ) {
			$wpdb->insert(
				BDWP70_Activator::verses_table(),
				array(
					'bible_id'   => $this->bible_id,
					'testamento' => $r[0] <= 39 ? 'OT' : 'NT',
					'livroseq'   => $r[0],
					'livro'      => $r[1],
					'capitulo'   => $r[2],
					'versiculo'  => $r[3],
					'palavra'    => $r[4],
					'published'  => 1,
					'hits'       => 0,
				),
				array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%d' )
			);
		}
		// One unpublished verse that random selection must never return.
		$wpdb->insert(
			BDWP70_Activator::verses_table(),
			array(
				'bible_id'   => $this->bible_id,
				'testamento' => 'NT',
				'livroseq'   => 43,
				'livro'      => 'John',
				'capitulo'   => 99,
				'versiculo'  => 99,
				'palavra'    => 'UNPUBLISHED',
				'published'  => 0,
				'hits'       => 0,
			),
			array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%d' )
		);
	}

	public function test_get_single_verse_returns_exact_row() {
		$plugin = BDWP70_Plugin::instance();
		$verse  = $plugin->get_single_verse( 43, 3, 16, $this->bible_id );
		$this->assertNotEmpty( $verse );
		$this->assertSame( 'For God so loved the world.', $verse->palavra );
	}

	public function test_get_chapter_verses_returns_all_chapter_rows() {
		$plugin = BDWP70_Plugin::instance();
		$verses = $plugin->get_chapter_verses( 43, 3, $this->bible_id );
		$this->assertCount( 2, $verses );
	}

	public function test_random_verse_stays_within_published_seeded_set() {
		$plugin  = BDWP70_Plugin::instance();
		$allowed = array( '43-3-16', '43-3-17', '19-23-1', '1-1-1' );

		for ( $i = 0; $i < 25; $i++ ) {
			BDWP70_Activator::clear_runtime_caches();
			$verse = $plugin->get_random_verse( 0, $this->bible_id );
			$this->assertNotEmpty( $verse, 'Random verse should never be empty with seeded data.' );
			$key = $verse->livroseq . '-' . $verse->capitulo . '-' . $verse->versiculo;
			$this->assertContains( $key, $allowed, "Random verse {$key} escaped the published set." );
			$this->assertNotSame( 'UNPUBLISHED', $verse->palavra );
		}
	}

	public function test_random_verse_constrained_by_book() {
		$plugin = BDWP70_Plugin::instance();
		$verse  = $plugin->get_random_verse( 19, $this->bible_id );
		$this->assertNotEmpty( $verse );
		$this->assertSame( 19, (int) $verse->livroseq );
	}
}
