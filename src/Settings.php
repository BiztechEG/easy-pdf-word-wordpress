<?php

namespace BiztechEG\EasyPdfWordWP;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's settings, kept in the easy_pdf_word_settings option. Each one
 * has a default, so the plugin works before the settings are saved.
 */
final class Settings {

	public const OPTION = 'easy_pdf_word_settings';

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			// standard: the tax invoice; zatca: with the Saudi ZATCA QR; egypt: the Egyptian e-invoice layout.
			'invoice_type'         => 'standard',
			// The seller's VAT number (Saudi) or tax registration number (Egypt).
			'tax_number'           => '',
			'invoice_prefix'       => 'INV-',
			'credit_note_prefix'   => 'CN-',
			// Digits of the sequential number: 4 gives INV-0001.
			'number_padding'       => 4,
			// Order statuses in which the customer can download the invoice.
			'invoice_statuses'     => array( 'processing', 'completed' ),
			// The documents customers can download from My account.
			'customer_documents'   => array( 'invoice', 'credit-note' ),
			// WooCommerce emails each document is attached to.
			'attach'               => array(
				'invoice'       => array( 'customer_completed_order', 'customer_invoice' ),
				'credit-note'   => array( 'customer_refunded_order' ),
				'delivery-note' => array(),
			),
		);
	}

	/** @return array<string, mixed> */
	public static function all(): array {
		$saved    = get_option( self::OPTION, array() );
		$settings = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );

		/**
		 * Filters the plugin's settings.
		 *
		 * @param array $settings The saved settings over the defaults.
		 */
		return (array) apply_filters( 'easy_pdf_word_settings', $settings );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? self::defaults()[ $key ] ?? null;
	}
}
