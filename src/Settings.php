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
			// The company on every document. Empty fields use the site name and WooCommerce's store address.
			'company_name'         => '',
			'company_address'      => '',
			'company_phone'        => '',
			'company_email'        => '',
			// A media library image.
			'logo'                 => 0,
			'primary_color'        => '#0F766E',
			// '' follows the site language (and the customer's, in WooCommerce emails).
			'language'             => '',
			'numerals'             => 'latin',
			'font'                 => 'cairo',
			// mpdf, or gotenberg: Chromium as a service at gotenberg_url.
			'engine'               => 'mpdf',
			'gotenberg_url'        => '',
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

	/**
	 * The company on the documents: the settings, else the site name and
	 * WooCommerce's store address.
	 *
	 * @return array{name: string, address: string, phone: string, email: string, tax_number: string}
	 */
	public static function company(): array {
		$settings = self::all();
		$name     = trim( (string) $settings['company_name'] );
		$address  = trim( (string) $settings['company_address'] );

		return array(
			// get_bloginfo() escapes for HTML; the templates escape again.
			'name'       => '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'address'    => '' !== $address ? $address : self::store_address(),
			'phone'      => trim( (string) $settings['company_phone'] ),
			'email'      => trim( (string) $settings['company_email'] ),
			'tax_number' => trim( (string) $settings['tax_number'] ),
		);
	}

	/** WooCommerce's store address on one line, or '' without WooCommerce. */
	public static function store_address(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}

		$address = WC()->countries->get_formatted_address(
			array(
				'address_1' => (string) get_option( 'woocommerce_store_address' ),
				'address_2' => (string) get_option( 'woocommerce_store_address_2' ),
				'city'      => (string) get_option( 'woocommerce_store_city' ),
				'postcode'  => (string) get_option( 'woocommerce_store_postcode' ),
				'country'   => strtok( (string) get_option( 'woocommerce_default_country' ), ':' ),
			),
			str_starts_with( get_locale(), 'ar' ) ? '، ' : ', '
		);

		return trim( html_entity_decode( wp_strip_all_tags( $address ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/** The logo's file, when the logo setting is an image in the media library. */
	public static function logo_path(): ?string {
		$id = (int) self::get( 'logo' );

		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return null;
		}

		$path = get_attached_file( $id );

		return is_string( $path ) && is_file( $path ) ? $path : null;
	}
}
