<?php
/**
 * PHPUnit bootstrap for the Biblia Digital integration test suite.
 *
 * @package BibliaDigital
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tmp       = getenv( 'TMPDIR' ) ? getenv( 'TMPDIR' ) : '/tmp';
	$_tests_dir = rtrim( $_tmp, '/' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Run bin/install-wp-tests.sh first." . PHP_EOL; // phpcs:ignore
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin's canonical entry file so its hooks register.
 */
function _bdwp70_manually_load_plugin() {
	require dirname( __DIR__ ) . '/biblia-digital.php';
}
tests_add_filter( 'muplugins_loaded', '_bdwp70_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
