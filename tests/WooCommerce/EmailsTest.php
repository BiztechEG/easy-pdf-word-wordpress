<?php

namespace BiztechEG\EasyPdfWordWP\Tests\WooCommerce;

use BiztechEG\EasyPdfWordWP\WooCommerce\Emails;

/**
 * Documents attached to WooCommerce emails.
 */
class EmailsTest extends TestCase {

	public function tear_down(): void {
		Emails::clean_up();
		parent::tear_down();
	}

	public function test_the_invoice_goes_with_the_completed_order_email(): void {
		$order = $this->order( array( 'status' => 'completed' ) );

		$attachments = Emails::attach( array( '/existing.pdf' ), 'customer_completed_order', $order );

		$this->assertCount( 2, $attachments );
		$this->assertSame( '/existing.pdf', $attachments[0] );
		$this->assertSame( 'invoice-INV-0001.pdf', basename( $attachments[1] ) );
		$this->assertStringStartsWith( '%PDF', (string) file_get_contents( $attachments[1] ) );
		$this->assertStringStartsWith( untrailingslashit( get_temp_dir() ) . '/easy-pdf-word/mail-', $attachments[1] );
		$this->assertSame( '700', decoct( fileperms( dirname( $attachments[1] ) ) & 0777 ), 'Other accounts cannot open the folder.' );
		$this->assertSame( '600', decoct( fileperms( $attachments[1] ) & 0777 ) );

		Emails::clean_up();
		$this->assertFileDoesNotExist( $attachments[1] );
		$this->assertDirectoryDoesNotExist( dirname( $attachments[1] ) );
	}

	public function test_no_invoice_for_an_order_that_is_not_paid(): void {
		$order = $this->order( array( 'status' => 'pending' ) );

		$this->assertSame( array(), Emails::attach( array(), 'customer_invoice', $order ) );
	}

	public function test_other_emails_get_nothing(): void {
		$order = $this->order();

		$this->assertSame( array(), Emails::attach( array(), 'customer_processing_order', $order ) );
		$this->assertSame( array(), Emails::attach( array(), 'customer_completed_order', 'not an order' ) );
	}

	public function test_the_credit_note_of_the_latest_refund_goes_with_the_refunded_email(): void {
		$order = $this->order();
		wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 10,
			)
		);
		wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 20,
			)
		);

		$attachments = Emails::attach( array(), 'customer_refunded_order', wc_get_order( $order->get_id() ) );

		$this->assertCount( 1, $attachments );
		$this->assertSame( 'credit-note-CN-0001.pdf', basename( $attachments[0] ), 'The first credit note made is for the latest refund.' );
		$refunds = wc_get_order( $order->get_id() )->get_refunds();
		$this->assertSame( 'CN-0001', $refunds[0]->get_meta( '_easy_pdf_word_credit_note_number' ) );
		$this->assertSame( '', $refunds[1]->get_meta( '_easy_pdf_word_credit_note_number' ) );
	}

	public function test_the_emails_follow_the_settings(): void {
		$this->settings(
			array(
				'attach' => array(
					'invoice'       => array(),
					'delivery-note' => array( 'new_order' ),
				),
			)
		);
		$order = $this->order( array( 'status' => 'completed' ) );

		$this->assertSame( array(), Emails::attach( array(), 'customer_completed_order', $order ) );

		$attachments = Emails::attach( array(), 'new_order', $order );
		$this->assertCount( 1, $attachments );
		$this->assertStringStartsWith( 'delivery-note-', basename( $attachments[0] ) );
	}

	public function test_a_document_that_fails_does_not_stop_the_email(): void {
		$this->settings( array( 'invoice_type' => 'egypt' ) ); // No tax registration number.
		$warnings = array();
		add_action(
			'easy_pdf_word_warning',
			function ( string $message ) use ( &$warnings ) {
				$warnings[] = $message;
			}
		);

		$attachments = Emails::attach( array(), 'customer_completed_order', $this->order( array( 'status' => 'completed' ) ) );

		$this->assertSame( array(), $attachments );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'tax registration number', $warnings[0] );
	}

	public function test_the_email_hook_is_registered(): void {
		$this->assertSame( 10, has_filter( 'woocommerce_email_attachments', array( Emails::class, 'attach' ) ) );
	}
}
