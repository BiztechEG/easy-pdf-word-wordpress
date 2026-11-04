<?php

namespace BiztechEG\EasyPdfWordWP\Tests\Plugin;

use WP_UnitTestCase;

/**
 * Deleting the plugin removes its options.
 */
class UninstallTest extends WP_UnitTestCase {

	public function test_uninstall_removes_the_settings_counters_and_locks(): void {
		update_option( 'easy_pdf_word_settings', array( 'company_name' => 'Noor' ) );
		update_option( 'easy_pdf_word_next_invoice', 41, false );
		update_option( 'easy_pdf_word_next_credit_note', 3, false );
		add_option( 'easy_pdf_word_lock_invoice_12', time(), '', false );
		update_option( 'blogname', 'kept' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'easy-pdf-word/easy-pdf-word.php' );
		}

		require dirname( __DIR__, 2 ) . '/uninstall.php';
		wp_cache_flush();

		$this->assertFalse( get_option( 'easy_pdf_word_settings' ) );
		$this->assertFalse( get_option( 'easy_pdf_word_next_invoice' ) );
		$this->assertFalse( get_option( 'easy_pdf_word_next_credit_note' ) );
		$this->assertFalse( get_option( 'easy_pdf_word_lock_invoice_12' ) );
		$this->assertSame( 'kept', get_option( 'blogname' ) );
	}
}
