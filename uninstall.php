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

// Number locks left by a request that died while it held one.
global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'easy_pdf_word_lock_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-time cleanup of the plugin's own options.
