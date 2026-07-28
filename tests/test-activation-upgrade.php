<?php
/**
 * Activation, schema creation and maybe_upgrade() idempotency / preservation.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Activation_Upgrade extends WP_UnitTestCase {

	private function tables() {
		return array(
			BDWP70_Activator::versions_table(),
			BDWP70_Activator::books_table(),
			BDWP70_Activator::verses_table(),
		);
	}

	private function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	private function index_exists( $table, $index ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM `' . esc_sql( $table ) . '` WHERE Key_name = %s', $index ) );
	}

	public function test_activation_creates_all_tables_and_options() {
		BDWP70_Activator::activate();

		foreach ( $this->tables() as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "Missing table {$table}" );
		}

		$this->assertSame( BDWP70_VERSION, get_option( BDWP70_Activator::OPTION_VERSION ) );
		$this->assertSame( 'biblia-digital', get_option( 'bdwp70_seo_base' ) );
		$this->assertNotFalse( get_option( 'bdwp70_sitemap_enabled' ) );
	}

	public function test_create_tables_ships_1_1_66_and_1_1_67_indexes() {
		BDWP70_Activator::create_tables();
		$verses = BDWP70_Activator::verses_table();

		// 1.1.66 schema.
		$this->assertTrue( $this->index_exists( $verses, 'bible_published_ref' ) );
		$this->assertTrue( $this->index_exists( $verses, 'palavra_fulltext' ) );
		// 1.1.67 schema.
		$this->assertTrue( $this->index_exists( $verses, 'bible_published_id' ) );
		$this->assertTrue( $this->index_exists( $verses, 'bible_published_book_id' ) );
	}

	public function test_maybe_upgrade_is_idempotent() {
		BDWP70_Activator::activate();
		$this->assertSame( BDWP70_VERSION, get_option( BDWP70_Activator::OPTION_VERSION ) );

		// Already current: must short-circuit and change nothing.
		delete_transient( 'bdwp70_upgrade_lock' );
		BDWP70_Activator::maybe_upgrade();
		BDWP70_Activator::maybe_upgrade();
		$this->assertSame( BDWP70_VERSION, get_option( BDWP70_Activator::OPTION_VERSION ) );
	}

	/**
	 * Simulates upgrading from an older version: options and imported data
	 * must survive, the version must advance, and 1.1.66/1.1.67 indexes must
	 * now be present on the previously-old install.
	 *
	 * @dataProvider legacy_versions
	 */
	public function test_upgrade_from_older_version_preserves_data( $old_version ) {
		global $wpdb;
		BDWP70_Activator::activate();

		// Seed one Bible version + a couple of books/verses + an admin setting.
		$bible_id = BDWP70_Activator::create_bible_version( 'Test Bible', 'pt-BR', 'unit', 0 );
		$this->assertGreaterThan( 0, $bible_id );
		update_option( BDWP70_Activator::OPTION_ACTIVE_BIBLE, $bible_id );
		update_option( 'bdwp70_seo_base', 'my-custom-base' );

		$wpdb->insert(
			BDWP70_Activator::verses_table(),
			array(
				'bible_id'   => $bible_id,
				'testamento' => 'NT',
				'livroseq'   => 43,
				'livro'      => 'John',
				'capitulo'   => 3,
				'versiculo'  => 16,
				'palavra'    => 'For God so loved the world.',
				'published'  => 1,
				'hits'       => 0,
			),
			array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%d' )
		);
		$verses_before = (int) BDWP70_Activator::count_verses();
		$this->assertGreaterThan( 0, $verses_before );

		// Pretend the stored schema version is old.
		update_option( BDWP70_Activator::OPTION_VERSION, $old_version );
		delete_transient( 'bdwp70_upgrade_lock' );

		BDWP70_Activator::maybe_upgrade();

		// Version advanced.
		$this->assertSame( BDWP70_VERSION, get_option( BDWP70_Activator::OPTION_VERSION ) );
		// Admin setting preserved (add_option must not overwrite).
		$this->assertSame( 'my-custom-base', get_option( 'bdwp70_seo_base' ) );
		$this->assertSame( (string) $bible_id, (string) get_option( BDWP70_Activator::OPTION_ACTIVE_BIBLE ) );
		// Imported data preserved.
		$this->assertSame( $verses_before, (int) BDWP70_Activator::count_verses() );
		// New-schema indexes now present on the upgraded install.
		$this->assertTrue( $this->index_exists( BDWP70_Activator::verses_table(), 'palavra_fulltext' ) );
	}

	public function legacy_versions() {
		return array(
			'from 1.1.65' => array( '1.1.65' ),
			'from 1.1.66' => array( '1.1.66' ),
			'from 1.1.67' => array( '1.1.67' ),
			'fresh (legacy loader, empty version)' => array( '' ),
		);
	}
}
