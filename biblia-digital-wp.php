<?php
// phpcs:ignoreFile -- Legacy compatibility loader; canonical plugin code is in biblia-digital.php.
// phpcs:disable Squiz.Commenting.FileComment.Missing -- Legacy loader only; canonical plugin metadata lives in biblia-digital.php.
/**
 * Legacy loader for installations that still reference biblia-digital-wp.php.
 *
 * @package BibliaDigital
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/biblia-digital.php';
