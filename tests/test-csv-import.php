<?php
/**
 * CSV import: a valid small fixture imports and updates state; a malformed
 * books.csv is rejected without creating a half-imported version.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Csv_Import extends WP_UnitTestCase {

	private $tmp = array();

	public function set_up() {
		parent::set_up();
		BDWP70_Activator::activate();
	}

	public function tear_down() {
		foreach ( $this->tmp as $f ) {
			if ( file_exists( $f ) ) {
				unlink( $f );
			}
		}
		$this->tmp = array();
		parent::tear_down();
	}

	private function write( $content ) {
		$path = wp_tempnam( 'bdwp70-csv-' );
		file_put_contents( $path, $content ); // phpcs:ignore
		$this->tmp[] = $path;
		return $path;
	}

	private function full_books_csv() {
		$lines = array( 'livro_seq,livro,livro_desc' );
		for ( $i = 1; $i <= 66; $i++ ) {
			$lines[] = $i . ',B' . $i . ',Book ' . $i;
		}
		return implode( "\n", $lines ) . "\n";
	}

	public function test_valid_import_creates_version_and_verses() {
		$books  = $this->write( $this->full_books_csv() );
		$verses = $this->write(
			"testamento,livroseq,livro,capitulo,versiculo,palavra\n"
			. "NT,43,B43,3,16,For God so loved the world.\n"
			. "OT,1,B1,1,1,In the beginning God created.\n"
		);

		$bible_id = BDWP70_Activator::import_uploaded_bible_from_csv( $books, $verses, 'Fixture', 'en_US', 'unit' );
		$this->assertGreaterThan( 0, $bible_id );
		$this->assertSame( 2, (int) BDWP70_Activator::count_verses( $bible_id ) );
		$this->assertSame( 66, (int) BDWP70_Activator::count_books( $bible_id ) );
		$this->assertSame( 'done', get_option( BDWP70_Activator::OPTION_STATUS ) );
		$this->assertSame( (string) $bible_id, (string) get_option( BDWP70_Activator::OPTION_ACTIVE_BIBLE ) );
	}

	public function test_incomplete_books_csv_is_rejected() {
		// Only 2 of 66 books -> parse_books_csv returns empty -> import fails.
		$books  = $this->write( "livro_seq,livro,livro_desc\n1,Gn,Genesis\n43,John,John\n" );
		$verses = $this->write( "testamento,livroseq,livro,capitulo,versiculo,palavra\nNT,43,John,3,16,x\n" );

		$result = BDWP70_Activator::import_uploaded_bible_from_csv( $books, $verses, 'Bad', 'en_US', 'unit' );
		$this->assertFalse( $result );
		$this->assertSame( 'error', get_option( BDWP70_Activator::OPTION_STATUS ) );
	}
}
