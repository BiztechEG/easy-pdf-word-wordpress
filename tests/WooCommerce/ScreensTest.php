<?php

namespace BiztechEG\EasyPdfWordWP\Tests\WooCommerce;

use BiztechEG\EasyPdfWordWP\WooCommerce\AdminOrders;
use BiztechEG\EasyPdfWordWP\WooCommerce\MyAccount;

/**
 * The document buttons on the order screens and in My account.
 */
class ScreensTest extends TestCase {

	public function test_the_order_box_lists_the_documents(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$order = $this->order();
		wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 10,
			)
		);

		ob_start();
		AdminOrders::render_box( wc_get_order( $order->get_id() ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Invoice', $html );
		$this->assertStringContainsString( 'Delivery note', $html );
		$this->assertStringContainsString( 'Credit note', $html );
		$this->assertSame( 6, substr_count( $html, 'action=easy_pdf_word_document' ), 'PDF and Word for each.' );
		$this->assertStringContainsString( '&#038;_wpnonce=', $html, 'Escaped links.' );
	}

	public function test_the_orders_list_has_an_invoice_button_for_managers(): void {
		$order = $this->order();

		$this->assertSame( array(), AdminOrders::list_action( array(), $order ) );
		$this->assertArrayNotHasKey( AdminOrders::BULK_ACTION, AdminOrders::bulk_actions( array() ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertArrayHasKey( 'easy_pdf_word_invoice', AdminOrders::list_action( array(), $order ) );
		$this->assertArrayHasKey( AdminOrders::BULK_ACTION, AdminOrders::bulk_actions( array() ) );
	}

	public function test_the_bulk_action_collects_the_invoices(): void {
		$first  = $this->order();
		$second = $this->order();

		$files = AdminOrders::invoices( array( $first->get_id(), $second->get_id(), 999999 ) );

		$this->assertSame( array( 'invoice-INV-0001.pdf', 'invoice-INV-0002.pdf' ), array_keys( $files ) );
		$this->assertSame( 'unchanged', AdminOrders::handle_bulk_action( 'unchanged', 'mark_completed', array( $first->get_id() ) ) );
	}

	public function test_my_account_shows_the_invoice_to_its_customer(): void {
		$order = $this->order();

		wp_set_current_user( $this->customer );
		$this->assertArrayHasKey( 'easy-pdf-word-invoice', MyAccount::order_actions( array(), $order ) );

		ob_start();
		MyAccount::order_documents( $order );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'document=invoice', $html );
		$this->assertStringNotContainsString( 'document=delivery-note', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertSame( array(), MyAccount::order_actions( array(), $order ) );

		ob_start();
		MyAccount::order_documents( $order );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_no_invoice_button_before_the_order_is_paid(): void {
		wp_set_current_user( $this->customer );

		$this->assertSame( array(), MyAccount::order_actions( array(), $this->order( array( 'status' => 'pending' ) ) ) );
	}
}
