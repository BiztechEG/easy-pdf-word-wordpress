<?php
/**
 * Removes the plugin's settings and number sequences when it is deleted.
 * Invoice and credit note numbers stay in the orders: they are records of
 * documents already given to customers.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'easy_pdf_word_settings', 'easy_pdf_word_next_invoice', 'easy_pdf_word_next_credit_note' ) as $easy_pdf_word_option ) {
	delete_option( $easy_pdf_word_option );
}
