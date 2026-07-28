<?php
/**
 * Multisite: a site created/activated while the plugin runs receives its own
 * site-local activation state, using that site's context.
 *
 * Note on scope: by default the WordPress PHPUnit harness rewrites CREATE TABLE
 * into CREATE TEMPORARY TABLE so each test rolls back cleanly. InnoDB rejects
 * FULLTEXT indexes on temporary tables, so the plugin's verses table — which
 * ships palavra_fulltext since 1.1.66 — cannot be created under that rewrite.
 * Tests that need the real schema therefore drop the rewrite filters and clean
 * up the tables themselves, the same approach WordPress core uses for its own
 * dbDelta tests. Only production-shaped tables are exercised here.
 *
 * @package BibliaDigital
 * @group ms-required
 */

class Test_BDWP70_Multisite extends WP_UnitTestCase {

	/**
	 * Blog IDs whose plugin tables must be dropped on tear-down.
	 *
	 * @var int[]
	 */
	private $real_schema_blogs = array();

	public function set_up() {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires a multisite test install (WP_TESTS_MULTISITE=1).' );
		}
	}

	public function tear_down() {
		global $wpdb;

		foreach ( $this->real_schema_blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( array( BDWP70_Activator::verses_table(), BDWP70_Activator::books_table(), BDWP70_Activator::versions_table() ) as $table ) {
				$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB
			}
			restore_current_blog();
		}
		$this->real_schema_blogs = array();

		parent::tear_down();
	}

	/**
	 * Lets the next CREATE TABLE produce a real table instead of a temporary one.
	 */
	private function allow_real_tables() {
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	private function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public function test_activation_writes_site_local_state_per_blog() {
		// Main site: activate and capture its own version option.
		BDWP70_Activator::activate_single_site();
		$this->assertSame( BDWP70_VERSION, get_option( BDWP70_Activator::OPTION_VERSION ) );
		update_option( 'bdwp70_seo_base', 'main-site-base' );

		// Second site: activation must run in the switched context and write
		// that site's own options, independently of the main site.
		$blog_id                   = self::factory()->blog->create();
		$this->real_schema_blogs[] = $blog_id;
		switch_to_blog( $blog_id );
		$this->allow_real_tables();

		$this->assertNotSame( 'main-site-base', get_option( 'bdwp70_seo_base' ), 'Options must be site-local, not shared.' );
		BDWP70_Activator::activate_single_site();
		$sub_version = get_option( BDWP70_Activator::OPTION_VERSION );
		$sub_base    = get_option( 'bdwp70_seo_base' );

		// The new site owns physical tables under its own prefix.
		$sub_verses = BDWP70_Activator::verses_table();
		$this->assertStringContainsString( (string) $blog_id, $sub_verses, 'Verses table must use the switched blog prefix.' );
		$this->assertTrue( $this->table_exists( $sub_verses ), 'New site did not receive its own verses table.' );
		$this->assertTrue( $this->table_exists( BDWP70_Activator::books_table() ) );
		$this->assertTrue( $this->table_exists( BDWP70_Activator::versions_table() ) );

		restore_current_blog();

		$this->assertSame( BDWP70_VERSION, $sub_version, 'New site did not receive its own activation version.' );
		$this->assertSame( 'biblia-digital', $sub_base, 'New site did not receive its own default SEO base.' );
		// Main site option preserved and independent.
		$this->assertSame( 'main-site-base', get_option( 'bdwp70_seo_base' ) );
	}

	public function test_activate_new_site_hook_is_guarded_when_not_network_active() {
		// When the plugin is not network-active, the wp_initialize_site callback
		// must be a no-op instead of erroring.
		$blog_id = self::factory()->blog->create();
		$site    = get_site( $blog_id );
		$this->assertInstanceOf( 'WP_Site', $site );

		// Should return cleanly (guard clause) without throwing.
		BDWP70_Activator::activate_new_site( $site );
		$this->assertTrue( true );
	}
}
