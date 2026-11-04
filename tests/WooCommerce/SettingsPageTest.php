<?php

namespace BiztechEG\EasyPdfWordWP\Tests\WooCommerce;

use BiztechEG\EasyPdfWordWP\Admin\SettingsPage;
use BiztechEG\EasyPdfWordWP\Settings;
use BiztechEG\EasyPdfWordWP\WooCommerce\Numbers;
use BiztechEG\EasyPdfWordWP\WooCommerce\OrderData;

/**
 * The WooCommerce part of the settings page.
 */
class SettingsPageTest extends TestCase {

	public function tear_down(): void {
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset between tests.
		parent::tear_down();
	}

	public function test_shop_managers_can_change_the_settings(): void {
		$this->assertSame( 'manage_woocommerce', SettingsPage::capability() );
		$this->assertTrue( user_can( self::factory()->user->create( array( 'role' => 'shop_manager' ) ), SettingsPage::capability() ) );
		$this->assertStringContainsString( 'admin.php?page=easy-pdf-word', SettingsPage::url() );
	}

	public function test_the_lists_keep_only_known_values(): void {
		$settings = SettingsPage::sanitize(
			array(
				'_form'              => '1',
				'_woocommerce'       => '1',
				'invoice_type'       => 'zatca',
				'customer_documents' => array( 'invoice', 'payslip' ),
				'invoice_statuses'   => array( 'completed', 'wc-completed', 'made-up' ),
				'attach'             => array(
					'invoice'     => array( 'customer_completed_order', 'customer_reset_password', 'nope' ),
					'credit-note' => 'customer_refunded_order',
					'unknown'     => array( 'new_order' ),
				),
			)
		);

		$this->assertSame( 'zatca', $settings['invoice_type'] );
		$this->assertSame( array( 'invoice' ), $settings['customer_documents'] );
		$this->assertSame( array( 'completed' ), $settings['invoice_statuses'] );
		$this->assertSame(
			array(
				'invoice'       => array( 'customer_completed_order' ),
				'credit-note'   => array( 'customer_refunded_order' ),
				'delivery-note' => array(),
			),
			$settings['attach']
		);
	}

	public function test_unticked_boxes_on_the_form_mean_none(): void {
		$settings = SettingsPage::sanitize(
			array(
				'_form'        => '1',
				'_woocommerce' => '1',
			)
		);

		$this->assertSame( array(), $settings['customer_documents'] );
		$this->assertSame( array(), $settings['attach']['invoice'] );
	}

	public function test_the_next_number_can_only_go_up(): void {
		Numbers::restart( 'invoice', 10 );

		SettingsPage::sanitize( array( 'next_invoice' => '250' ) );
		$this->assertSame( 250, Numbers::peek( 'invoice' ) );

		SettingsPage::sanitize( array( 'next_invoice' => '5' ) );
		$this->assertSame( 250, Numbers::peek( 'invoice' ), 'Lower numbers would repeat invoice numbers.' );
		$this->assertSame( 'next_invoice', get_settings_errors( Settings::OPTION )[0]['code'] );

		$this->assertSame( 'INV-0250', Numbers::for( 'invoice', $this->order() )['number'] );
		$this->assertArrayNotHasKey( 'next_invoice', SettingsPage::sanitize( array( 'next_invoice' => '300' ) ) );
	}

	public function test_the_company_settings_are_the_seller(): void {
		$this->settings(
			array(
				'company_name'    => 'شركة النور للتجارة',
				'company_address' => 'جدة، حي الروضة',
				'tax_number'      => '300000000000003',
			)
		);

		$seller = OrderData::invoice( $this->order() )['seller'];

		$this->assertSame( 'شركة النور للتجارة', $seller['name'] );
		$this->assertSame( 'جدة، حي الروضة', $seller['address'] );
		$this->assertSame( '300000000000003', $seller['tax_number'] );
	}

	public function test_the_email_choices_are_order_emails(): void {
		$emails = SettingsPage::emails();

		$this->assertArrayHasKey( 'customer_completed_order', $emails );
		$this->assertArrayHasKey( 'customer_invoice', $emails );
		$this->assertArrayHasKey( 'new_order', $emails );
		$this->assertArrayNotHasKey( 'customer_reset_password', $emails );
		$this->assertStringContainsString( '(to the customer)', $emails['customer_completed_order'] );
		$this->assertStringContainsString( '(to the shop)', $emails['new_order'] );
	}

	public function test_the_sample_invoice_follows_the_invoice_type(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( SettingsPage::PREVIEW );

		foreach ( array( 'standard', 'zatca', 'egypt' ) as $type ) {
			$this->settings(
				array(
					'invoice_type' => $type,
					'tax_number'   => '300000000000003',
				)
			);

			$this->assertStringStartsWith( '%PDF', SettingsPage::sample_file()->content(), $type );
		}
	}
}
