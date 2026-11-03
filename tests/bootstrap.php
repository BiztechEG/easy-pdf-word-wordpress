<?php
/**
 * Loads WordPress's test suite with the plugin active.
 *
 * Needs a MySQL database for tests (WP_TESTS_DB_NAME, WP_TESTS_DB_USER,
 * WP_TESTS_DB_PASSWORD, WP_TESTS_DB_HOST) and the prefixed libraries
 * (composer prefix). See CONTRIBUTING.md.
 */

$root = dirname( __DIR__ );

$composer = require $root . '/vendor/autoload.php';

// Like the plugin zip: the libraries only load from vendor-prefixed/.
$libraries = array(
	'BiztechEG\\EasyPdfWord\\',
	'Mpdf\\',
	'Mpdf\\PsrLogAwareTrait\\',
	'Mpdf\\PsrHttpMessageShim\\',
	'PhpOffice\\PhpWord\\',
	'PhpOffice\\Math\\',
	'chillerlan\\QRCode\\',
	'chillerlan\\Settings\\',
	'setasign\\Fpdi\\',
	'DeepCopy\\',
);

foreach ( $libraries as $namespace ) {
	$composer->setPsr4( $namespace, array() );
}

if ( ! is_file( $root . '/vendor-prefixed/autoload.php' ) ) {
	fwrite( STDERR, "Run `composer prefix` first: the plugin loads its libraries from vendor-prefixed/.\n" );
	exit( 1 );
}

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ?: $root . '/vendor/wp-phpunit/wp-phpunit';

require $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $root ) {
		require $root . '/easy-pdf-word.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
