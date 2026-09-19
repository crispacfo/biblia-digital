<?php
/**
 * Tests for converting custom translations made for the Portuguese source strings (<= 1.1.80).
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Legacy_Translations extends WP_UnitTestCase {

	private $tmp_files = array();

	public function set_up() {
		parent::set_up();
		require_once ABSPATH . WPINC . '/pomo/mo.php';
		require_once ABSPATH . WPINC . '/pomo/po.php';
	}

	public function tear_down() {
		foreach ( $this->tmp_files as $f ) {
			if ( file_exists( $f ) ) {
				unlink( $f );
			}
		}
		$this->tmp_files = array();
		parent::tear_down();
	}

	private function tmp( $ext ) {
		$path              = wp_tempnam( 'bdwp70-l10n-' ) . '.' . $ext;
		$this->tmp_files[] = $path;
		return $path;
	}

	/**
	 * Builds a catalog keyed by the old Portuguese msgids, as a Spanish site would have uploaded.
	 */
	private function legacy_catalog( $catalog ) {
		$catalog->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$catalog->set_header( 'Plural-Forms', 'nplurals=2; plural=(n != 1);' );
		$catalog->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'Pesquisar na Bíblia',
					'translations' => array( 'Buscar en la Biblia' ),
				)
			)
		);
		$catalog->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'Buscar na Bíblia',
					'translations' => array( 'Buscar dentro de la Biblia' ),
				)
			)
		);
		$catalog->add_entry(
			new Translation_Entry(
				array(
					'singular'     => '%1$s resultado encontrado para "%2$s".',
					'plural'       => '%1$s resultados encontrados para "%2$s".',
					'translations' => array( '%1$s resultado para "%2$s".', '%1$s resultados para "%2$s".' ),
				)
			)
		);
		$catalog->add_entry(
			new Translation_Entry(
				array(
					'context'      => 'block title',
					'singular'     => 'Bíblia Digital - Busca',
					'translations' => array( 'Bíblia Digital - Búsqueda' ),
				)
			)
		);
		// Unknown key: must survive untouched.
		$catalog->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'Texto que não existe no plugin',
					'translations' => array( 'Texto que no existe' ),
				)
			)
		);
		return $catalog;
	}

	private function assert_converted( $catalog ) {
		$this->assertSame( 'Buscar en la Biblia', $catalog->translate( 'Search the Bible' ) );
		$this->assertSame( 'Buscar dentro de la Biblia', $catalog->translate( 'Search in the Bible' ) );
		$this->assertSame( '%1$s resultados para "%2$s".', $catalog->translate_plural( '%1$s result found for "%2$s".', '%1$s results found for "%2$s".', 3 ) );
		$this->assertSame( 'Bíblia Digital - Búsqueda', $catalog->translate( 'Bíblia Digital - Search', 'block title' ) );
		$this->assertSame( 'Texto que no existe', $catalog->translate( 'Texto que não existe no plugin' ) );
		// The old key is gone.
		$this->assertSame( 'Pesquisar na Bíblia', $catalog->translate( 'Pesquisar na Bíblia' ) );
	}

	public function test_map_targets_are_unique_and_english_keys_exist_in_code() {
		$map     = BDWP70_Plugin::legacy_msgid_map();
		$targets = array();
		foreach ( $map as $hash => $pair ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $hash );
			$this->assertIsArray( $pair );
			$this->assertCount( 3, $pair );
			// The context is kept on conversion, so the new key is context + msgid.
			$targets[] = ( null === $pair[2] ? '' : $pair[2] ) . "\x04" . $pair[0];
		}
		$this->assertGreaterThan( 300, count( $map ) );
		// Two old strings mapped to one new key would lose a translation.
		$this->assertSame( count( $targets ), count( array_unique( $targets ) ) );
	}

	public function test_po_file_is_converted() {
		$file = $this->tmp( 'po' );
		$this->assertTrue( $this->legacy_catalog( new PO() )->export_to_file( $file ) );

		$this->assertSame( 4, BDWP70_Plugin::remap_legacy_translation_file( $file ) );

		$po = new PO();
		$this->assertTrue( $po->import_from_file( $file ) );
		$this->assert_converted( $po );
	}

	public function test_mo_file_is_converted() {
		$file = $this->tmp( 'mo' );
		$this->assertTrue( $this->legacy_catalog( new MO() )->export_to_file( $file ) );

		$this->assertSame( 4, BDWP70_Plugin::remap_legacy_translation_file( $file ) );

		$mo = new MO();
		$this->assertTrue( $mo->import_from_file( $file ) );
		$this->assert_converted( $mo );
	}

	public function test_already_converted_file_is_left_alone() {
		$file = $this->tmp( 'mo' );
		$this->legacy_catalog( new MO() )->export_to_file( $file );
		BDWP70_Plugin::remap_legacy_translation_file( $file );
		$hash_antes = md5_file( $file );

		$this->assertSame( 0, BDWP70_Plugin::remap_legacy_translation_file( $file ) );
		$this->assertSame( $hash_antes, md5_file( $file ) );
	}

	public function test_other_extensions_are_ignored() {
		$file = $this->tmp( 'pot' );
		file_put_contents( $file, "msgid \"\"\nmsgstr \"\"\n" );
		$this->assertFalse( BDWP70_Plugin::remap_legacy_translation_file( $file ) );
	}
}
