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

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- how wp-phpunit finds its config.

$tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ?: $root . '/vendor/wp-phpunit/wp-phpunit';

require $tests_dir . '/includes/functions.php';

// WooCommerce, when WC_DIR names its folder (CI downloads its release zip).
$woocommerce = getenv( 'WC_DIR' ) ? rtrim( getenv( 'WC_DIR' ), '/' ) . '/woocommerce.php' : '';

if ( $woocommerce && ! is_file( $woocommerce ) ) {
	fwrite( STDERR, "WC_DIR is set, but {$woocommerce} does not exist.\n" );
	exit( 1 );
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $root, $woocommerce ) {
		if ( $woocommerce ) {
			require $woocommerce;
		}

		require $root . '/easy-pdf-word.php';
	}
);

if ( $woocommerce ) {
	// WooCommerce's tables, roles and pages, as its own test suite installs them.
	tests_add_filter(
		'setup_theme',
		static function () {
			WC_Install::install();
			$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reload the roles WooCommerce added.
			wp_roles();
		}
	);
}

require $tests_dir . '/includes/bootstrap.php';
