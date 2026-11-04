<?php

namespace BiztechEG\EasyPdfWordWP\Tests\WooCommerce;

use BiztechEG\EasyPdfWordWP\WooCommerce\Downloads;
use WPDieException;

/**
 * Download links: who may download which document.
 */
class DownloadsTest extends TestCase {

	/** The query of a download link, as the visitor's browser sends it. */
	private static function query( string $url ): array {
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		return $query;
	}

	public function test_the_customer_downloads_their_paid_order_invoice(): void {
		$order = $this->order();
		wp_set_current_user( $this->customer );

		$file = Downloads::request( self::query( Downloads::url( 'invoice', $order ) ) );

		$this->assertStringStartsWith( '%PDF', $file->content() );
		$this->assertSame( 'invoice-INV-0001.pdf', $file->filename() );
	}

	public function test_customers_can_download_only_what_the_settings_allow(): void {
		$order = $this->order();

		$this->assertTrue( Downloads::can_download( 'invoice', $order, $this->customer ) );
		$this->assertTrue( Downloads::can_download( 'credit-note', $order, $this->customer ) );
		$this->assertFalse( Downloads::can_download( 'delivery-note', $order, $this->customer ), 'Not by default.' );

		$pending = $this->order( array( 'status' => 'pending' ) );
		$this->assertFalse( Downloads::can_download( 'invoice', $pending, $this->customer ), 'Not before it is paid.' );

		$other = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->assertFalse( Downloads::can_download( 'invoice', $order, $other ) );
		$this->assertFalse( Downloads::can_download( 'invoice', $order, 0 ) );

		$this->settings( array( 'customer_documents' => array( 'invoice', 'credit-note', 'delivery-note' ) ) );
		$this->assertTrue( Downloads::can_download( 'delivery-note', $order, $this->customer ) );
	}

	public function test_shop_managers_can_download_any_document(): void {
		$manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$pending = $this->order( array( 'status' => 'pending' ) );

		foreach ( array( 'invoice', 'credit-note', 'delivery-note' ) as $type ) {
			$this->assertTrue( Downloads::can_download( $type, $pending, $manager ) );
		}
	}

	public function test_another_customer_is_refused_even_with_the_link(): void {
		$order = $this->order();

		// A nonce belongs to the user it was made for, so the other customer gets their own.
		$other = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $other );
		$query = self::query( Downloads::url( 'invoice', $order ) );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'not allowed' );
		Downloads::request( $query );
	}

	public function test_a_link_without_its_nonce_is_refused(): void {
		$order = $this->order();
		wp_set_current_user( $this->customer );
		$query = self::query( Downloads::url( 'invoice', $order ) );

		$query['_wpnonce'] = 'wrong';
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'expired' );
		Downloads::request( $query );
	}

	public function test_a_nonce_is_for_one_document_of_one_order(): void {
		$order = $this->order();
		$other = $this->order();
		wp_set_current_user( $this->customer );

		$query          = self::query( Downloads::url( 'invoice', $order ) );
		$query['order'] = $other->get_id();

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'expired' );
		Downloads::request( $query );
	}

	public function test_unknown_documents_and_refunds_of_other_orders_are_not_found(): void {
		$order  = $this->order();
		$other  = $this->order();
		$refund = wc_create_refund(
			array(
				'order_id' => $other->get_id(),
				'amount'   => 10,
			)
		);
		wp_set_current_user( $this->customer );

		$query   = self::query( Downloads::url( 'invoice', $order ) );
		$changes = array(
			array( 'document' => 'payslip' ),
			array( 'document' => array( 'invoice' ) ),
			array( 'format' => 'html' ),
			array( 'order' => 999999 ),
			array( 'refund' => $refund->get_id() ),
		);

		foreach ( $changes as $change ) {
			try {
				Downloads::request( array_merge( $query, $change ) );
				$this->fail( 'Expected not found for ' . wp_json_encode( $change ) );
			} catch ( WPDieException $e ) {
				$this->assertStringContainsString( 'not found', $e->getMessage() );
			}
		}
	}

	public function test_credit_notes_and_word_files(): void {
		$order  = $this->order();
		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 20,
			)
		);
		wp_set_current_user( $this->customer );

		$credit = Downloads::request( self::query( Downloads::url( 'credit-note', $order, $refund ) ) );
		$this->assertStringStartsWith( '%PDF', $credit->content() );
		$this->assertSame( 'credit-note-CN-0001.pdf', $credit->filename() );

		$word = Downloads::request( self::query( Downloads::url( 'invoice', $order, null, 'docx' ) ) );
		$this->assertStringStartsWith( 'PK', $word->content() );
	}

	public function test_customers_do_not_see_why_a_document_failed(): void {
		$this->settings( array( 'invoice_type' => 'egypt' ) ); // No tax registration number.
		$order = $this->order();

		wp_set_current_user( $this->customer );
		try {
			Downloads::request( self::query( Downloads::url( 'invoice', $order ) ) );
			$this->fail( 'Expected an error page.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'contact the shop', $e->getMessage() );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'tax registration number' );
		Downloads::request( self::query( Downloads::url( 'invoice', $order ) ) );
	}

	public function test_visitors_who_are_not_logged_in_go_to_the_login_form(): void {
		$this->assertSame( 10, has_action( 'admin_post_' . Downloads::ACTION, array( Downloads::class, 'handle' ) ) );
		$this->assertSame( 10, has_action( 'admin_post_nopriv_' . Downloads::ACTION, array( Downloads::class, 'to_login' ) ) );
	}
}
