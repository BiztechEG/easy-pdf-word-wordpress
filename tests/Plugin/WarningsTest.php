<?php

namespace BiztechEG\EasyPdfWordWP\Tests\Plugin;

use BiztechEG\EasyPdfWordWP\Plugin;
use WP_UnitTestCase;

/**
 * Warnings reach the PHP error log with WP_DEBUG, whoever raises them.
 */
class WarningsTest extends WP_UnitTestCase {

	public function test_every_warning_reaches_the_error_log(): void {
		$this->assertSame( 10, has_action( 'easy_pdf_word_warning', array( Plugin::class, 'log_warning' ) ) );

		$log      = get_temp_dir() . 'easy-pdf-word-log-' . wp_generate_password( 6, false ) . '.txt';
		$previous = ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- the test reads the log.

		try {
			do_action( 'easy_pdf_word_warning', 'Invoice of order 12 was not attached: test' );
		} finally {
			ini_set( 'error_log', (string) $previous ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		}

		$this->assertStringContainsString( 'Easy PDF & Word: Invoice of order 12 was not attached: test', (string) file_get_contents( $log ) );
		wp_delete_file( $log );
	}
}
