<?php

namespace BiztechEG\EasyPdfWordWP\Tests\WooCommerce;

use BiztechEG\EasyPdfWordWP\WooCommerce\Numbers;
use BiztechEG\EasyPdfWordWP\WooCommerce\OrderData;
use BiztechEG\EasyPdfWordWP\WooCommerce\OrderDocument;
use RuntimeException;

/**
 * Orders read into the templates' data, with totals that match the order.
 */
class OrderDataTest extends TestCase {

	public function test_the_invoice_matches_the_order(): void {
		$order = $this->order();
		$data  = OrderData::invoice( $order );

		$this->assertSame( 'INV-0001', $data['invoice']['number'] );
		$this->assertSame( current_datetime()->format( 'Y-m-d' ), $data['invoice']['date'] );
		$this->assertSame( 'SAR', $data['invoice']['currency'] );
		$this->assertSame( 15.0, $data['invoice']['tax_rate'] );
		$this->assertNull( $data['qr'] );

		$this->assertCount( 2, $data['items'], 'The product and the shipping.' );
		$this->assertSame( 'استشارة', $data['items'][0]['description'] );
		$this->assertEquals( 2, $data['items'][0]['quantity'] );
		$this->assertEquals( 100, $data['items'][0]['unit_price'] );
		$this->assertSame( 'Flat rate', $data['items'][1]['description'] );
		$this->assertEquals( 20, $data['items'][1]['unit_price'] );

		$this->assertEqualsWithDelta( (float) $order->get_total(), self::total( $data['items'], 15 ), 0.001 );
	}

	public function test_the_seller_is_the_shop_and_the_buyer_the_billing_customer(): void {
		$this->settings( array( 'tax_number' => '300000000000003' ) );
		$data = OrderData::invoice( $this->order() );

		$this->assertSame( 'Noor Store', $data['seller']['name'] );
		$this->assertSame( '300000000000003', $data['seller']['tax_number'] );
		$this->assertStringContainsString( 'King Fahd Road', $data['seller']['address'] );
		$this->assertStringContainsString( 'Riyadh', $data['seller']['address'] );

		$this->assertSame( 'عمرو محمد', $data['buyer']['name'] );
		$this->assertStringContainsString( 'شارع التحلية', $data['buyer']['address'] );
		$this->assertStringNotContainsString( 'عمرو', $data['buyer']['address'], 'The name is not repeated in the address.' );

		$company = OrderData::invoice( $this->order( array( 'company' => 'مؤسسة النور' ) ) );
		$this->assertSame( 'مؤسسة النور', $company['buyer']['name'] );
	}

	public function test_a_coupon_is_a_discount_on_the_lines(): void {
		$order = $this->order( array( 'coupon' => 50 ) );
		$data  = OrderData::invoice( $order );

		$this->assertEqualsWithDelta( 50, $data['items'][0]['discount'], 0.001 );
		$this->assertEqualsWithDelta( (float) $order->get_total(), self::total( $data['items'], 15 ), 0.001 );
	}

	public function test_a_fee_is_a_line_and_a_negative_fee_a_discount(): void {
		$fee  = $this->order( array( 'fee' => 10 ) );
		$data = OrderData::invoice( $fee );

		$this->assertCount( 3, $data['items'] );
		$this->assertEqualsWithDelta( (float) $fee->get_total(), self::total( $data['items'], 15 ), 0.001 );

		$credit = $this->order( array( 'fee' => -30 ) );
		$data   = OrderData::invoice( $credit );

		$this->assertCount( 2, $data['items'], 'A negative fee is not a line.' );
		$this->assertEqualsWithDelta( 30, $data['items'][0]['discount'], 0.001 );
		$this->assertEqualsWithDelta( (float) $credit->get_total(), self::total( $data['items'], 15 ), 0.001 );
	}

	public function test_an_order_without_tax_has_a_zero_rate(): void {
		update_option( 'woocommerce_calc_taxes', 'no' );
		$order = $this->order();

		$this->assertSame( 0.0, OrderData::tax_rate( $order ) );
		$this->assertEqualsWithDelta( (float) $order->get_total(), self::total( OrderData::invoice( $order )['items'], 0 ), 0.001 );
	}

	public function test_invoice_numbers_are_sequential_and_kept(): void {
		$first  = $this->order();
		$second = $this->order();

		$this->assertSame( 'INV-0001', Numbers::for( 'invoice', $first )['number'] );
		$this->assertSame( 'INV-0002', Numbers::for( 'invoice', $second )['number'] );
		$this->assertSame( 'INV-0001', Numbers::for( 'invoice', wc_get_order( $first->get_id() ) )['number'], 'Kept in the order.' );
		$this->assertNull( Numbers::existing( 'invoice', $this->order() ) );
	}

	public function test_the_number_format_follows_the_settings(): void {
		$this->settings(
			array(
				'invoice_prefix' => 'F{year}-',
				'number_padding' => 6,
			)
		);
		Numbers::restart( 'invoice', 120 );

		$this->assertSame( 'F' . current_datetime()->format( 'Y' ) . '-000120', Numbers::for( 'invoice', $this->order() )['number'] );
	}

	public function test_the_zatca_invoice_has_the_qr(): void {
		$this->settings(
			array(
				'invoice_type' => 'zatca',
				'tax_number'   => '300000000000003',
			)
		);

		$this->assertSame( 'zatca', OrderData::invoice( $this->order() )['qr'] );
	}

	public function test_the_egyptian_invoice_needs_the_tax_registration_number(): void {
		$this->settings( array( 'invoice_type' => 'egypt' ) );

		$this->expectException( RuntimeException::class );
		OrderData::egypt( $this->order() );
	}

	public function test_the_egyptian_invoice_has_vat_on_each_line(): void {
		$this->settings(
			array(
				'invoice_type' => 'egypt',
				'tax_number'   => '123-456-789',
			)
		);
		update_option( 'timezone_string', 'Africa/Cairo' );
		$data = OrderData::egypt( $this->order( array( 'company' => 'مؤسسة النور' ) ) );

		$this->assertSame( 'I', $data['document']['type'] );
		$this->assertStringStartsWith( current_datetime()->format( 'Y-m-d H:' ), $data['document']['issued_at'], 'Issued at the local time.' );
		$this->assertSame( 'INV-0001', $data['document']['internal_id'] );
		$this->assertSame( '123-456-789', $data['issuer']['rin'] );
		$this->assertSame( 'B', $data['receiver']['type'] );
		$this->assertSame(
			array(
				array(
					'type' => 'T1',
					'rate' => 15.0,
				),
			),
			$data['lines'][0]['taxes']
		);
		$this->assertArrayNotHasKey( 'rate', $data['lines'][0] );
	}

	public function test_a_credit_note_for_refunded_items(): void {
		$order  = $this->order();
		$item   = array_values( $order->get_items() )[0];
		$refund = wc_create_refund(
			array(
				'order_id'   => $order->get_id(),
				'amount'     => 115,
				'reason'     => 'منتج تالف',
				'line_items' => array(
					$item->get_id() => array(
						'qty'          => 1,
						'refund_total' => 100,
						'refund_tax'   => array( array_key_first( $item->get_taxes()['total'] ) => 15 ),
					),
				),
			)
		);

		$data = OrderData::credit_note( $order, $refund );

		$this->assertSame( 'CN-0001', $data['note']['number'] );
		$this->assertSame( 'INV-0001', $data['invoice']['number'], 'The invoice it corrects.' );
		$this->assertSame( 'منتج تالف', $data['reason'] );
		$this->assertCount( 1, $data['items'] );
		$this->assertEquals( 1, $data['items'][0]['quantity'] );
		$this->assertEqualsWithDelta( 100, $data['items'][0]['unit_price'], 0.001 );
		$this->assertEqualsWithDelta( 115, self::total( $data['items'], $data['note']['tax_rate'] ), 0.001 );
	}

	public function test_a_credit_note_for_an_amount(): void {
		$order  = $this->order();
		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 50,
			)
		);

		$data = OrderData::credit_note( $order, $refund );

		$this->assertSame( 'Refund', $data['reason'] );
		$this->assertSame( array( 'description', 'quantity', 'unit_price' ), array_keys( $data['items'][0] ) );
		$this->assertEqualsWithDelta( 50, $data['items'][0]['unit_price'], 0.001 );
	}

	public function test_the_delivery_note_leaves_out_refunded_items(): void {
		$order = $this->order();
		$item  = array_values( $order->get_items() )[0];
		wc_create_refund(
			array(
				'order_id'   => $order->get_id(),
				'amount'     => 100,
				'line_items' => array(
					$item->get_id() => array(
						'qty'          => 1,
						'refund_total' => 100,
					),
				),
			)
		);

		$data = OrderData::delivery_note( wc_get_order( $order->get_id() ) );

		$this->assertSame( (string) $order->get_order_number(), $data['delivery']['order_number'] );
		$this->assertNull( $data['delivery']['invoice_number'] );
		$this->assertCount( 1, $data['items'] );
		$this->assertEquals( 1, $data['items'][0]['quantity'] );
		$this->assertStringStartsWith( 'SKU-', $data['items'][0]['code'] );
		$this->assertSame( '0500000000', $data['customer']['phone'] );
		$this->assertStringContainsString( 'جدة', $data['ship_to']['address'] );
	}

	public function test_every_document_renders_in_arabic_and_english(): void {
		$this->settings( array( 'tax_number' => '300000000000003' ) );

		foreach ( array( 'ar', 'en_US' ) as $locale ) {
			$filter = fn () => $locale;
			add_filter( 'locale', $filter );

			$order  = $this->order();
			$refund = wc_create_refund(
				array(
					'order_id' => $order->get_id(),
					'amount'   => 20,
				)
			);

			foreach ( array( 'invoice', 'delivery-note', 'credit-note' ) as $type ) {
				$file = OrderDocument::file( $type, $order, 'credit-note' === $type ? $refund : null );
				$this->assertStringStartsWith( '%PDF', $file->content(), "{$type} in {$locale}" );
			}

			$this->assertStringStartsWith( 'PK', OrderDocument::file( 'invoice', $order, null, 'docx' )->content() );

			foreach ( array( 'zatca', 'egypt' ) as $invoice_type ) {
				$this->settings(
					array(
						'invoice_type' => $invoice_type,
						'tax_number'   => '300000000000003',
					)
				);
				$this->assertStringStartsWith( '%PDF', OrderDocument::file( 'invoice', $order )->content(), "{$invoice_type} in {$locale}" );
				$this->assertStringStartsWith( '%PDF', OrderDocument::file( 'credit-note', $order, $refund )->content() );
			}

			$this->settings( array( 'tax_number' => '300000000000003' ) );
			remove_filter( 'locale', $filter );
		}
	}

	public function test_the_data_and_template_can_be_filtered(): void {
		add_filter(
			'easy_pdf_word_woocommerce_data',
			function ( array $data, string $type ) {
				if ( 'invoice' === $type ) {
					$data['invoice']['notes'] = 'شكراً لتعاملكم معنا';
				}

				return $data;
			},
			10,
			2
		);

		$html = OrderDocument::make( 'invoice', $this->order() )->toHtml();
		$this->assertStringContainsString( 'شكراً لتعاملكم معنا', $html );

		add_filter( 'easy_pdf_word_woocommerce_template', fn () => 'no-such-template' );
		$this->expectExceptionMessage( 'no-such-template' );
		OrderDocument::make( 'invoice', $this->order() );
	}

	public function test_a_credit_note_needs_a_refund_of_the_same_order(): void {
		$order  = $this->order();
		$other  = $this->order();
		$refund = wc_create_refund(
			array(
				'order_id' => $other->get_id(),
				'amount'   => 10,
			)
		);

		$this->expectException( \InvalidArgumentException::class );
		OrderDocument::make( 'credit-note', $order, $refund );
	}

	public function test_file_names_carry_the_number(): void {
		$order = $this->order();
		$file  = OrderDocument::file( 'invoice', $order );

		$this->assertSame( 'invoice-INV-0001.pdf', $file->filename() );
		$this->assertSame( 'invoice-INV-0001.docx', OrderDocument::filename( 'invoice', $order, null, 'docx' ) );
	}
}
