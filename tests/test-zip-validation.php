<?php
/**
 * Security tests for the uploaded-ZIP archive validator.
 *
 * @package BibliaDigital
 */

class Test_BDWP70_Zip_Validation extends WP_UnitTestCase {

	private $tmp_files = array();

	public function tear_down() {
		foreach ( $this->tmp_files as $f ) {
			if ( file_exists( $f ) ) {
				unlink( $f );
			}
		}
		$this->tmp_files = array();
		parent::tear_down();
	}

	private function validate( $zip_path ) {
		$plugin = BDWP70_Plugin::instance();
		$ref    = new ReflectionMethod( $plugin, 'validate_uploaded_zip_archive' );
		// Private methods are reflection-accessible by default since PHP 8.1, and
		// setAccessible() is deprecated on PHP 8.5+. Only call it where needed.
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}
		return $ref->invoke( $plugin, $zip_path );
	}

	private function make_zip( array $entries ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ext-zip is required to build test archives.' );
		}
		$path = wp_tempnam( 'bdwp70-test-' ) . '.zip';
		$this->tmp_files[] = $path;
		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		foreach ( $entries as $name => $content ) {
			$zip->addFromString( $name, $content );
		}
		$zip->close();
		return $path;
	}

	public function test_valid_zip_passes() {
		$path = $this->make_zip(
			array(
				'books.csv'  => "livro_seq,livro,livro_desc\n1,Gn,Genesis\n",
				'verses.csv' => "testamento,livroseq,livro,capitulo,versiculo,palavra\nOT,1,Gn,1,1,In the beginning.\n",
			)
		);
		$this->assertTrue( $this->validate( $path ) );
	}

	public function test_path_traversal_is_rejected() {
		$path = $this->make_zip(
			array(
				'../evil.csv' => 'x',
				'books.csv'   => 'x',
				'verses.csv'  => 'x',
			)
		);
		$result = $this->validate( $path );
		$this->assertWPError( $result );
		$this->assertSame( 'bdwp70_zip_traversal', $result->get_error_code() );
	}

	public function test_unexpected_file_is_rejected() {
		$path = $this->make_zip(
			array(
				'books.csv'  => 'x',
				'verses.csv' => 'x',
				'malware.php' => '<?php echo 1;',
			)
		);
		$result = $this->validate( $path );
		$this->assertWPError( $result );
		$this->assertSame( 'bdwp70_zip_unexpected_file', $result->get_error_code() );
	}

	public function test_missing_required_file_is_rejected() {
		$path   = $this->make_zip( array( 'books.csv' => 'x' ) );
		$result = $this->validate( $path );
		$this->assertWPError( $result );
		$this->assertSame( 'bdwp70_zip_missing_file', $result->get_error_code() );
	}

	public function test_oversized_extracted_content_is_rejected() {
		$filter = static function () {
			return 8; // 8 bytes cap forces the size guard.
		};
		add_filter( 'bdwp70_upload_max_extracted_size', $filter );
		$path = $this->make_zip(
			array(
				'books.csv'  => str_repeat( 'A', 4096 ),
				'verses.csv' => str_repeat( 'B', 4096 ),
			)
		);
		$result = $this->validate( $path );
		remove_filter( 'bdwp70_upload_max_extracted_size', $filter );
		$this->assertWPError( $result );
		$this->assertSame( 'bdwp70_zip_total_size', $result->get_error_code() );
	}

	public function test_corrupt_zip_is_rejected() {
		$path = wp_tempnam( 'bdwp70-bad-' ) . '.zip';
		$this->tmp_files[] = $path;
		file_put_contents( $path, 'this is not a zip file' ); // phpcs:ignore
		$result = $this->validate( $path );
		$this->assertWPError( $result );
		$this->assertSame( 'bdwp70_zip_open', $result->get_error_code() );
	}
}
