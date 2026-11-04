<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use BiztechEG\EasyPdfWordWP\Settings;
use RuntimeException;
use WC_Abstract_Order;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a WooCommerce order into the data of the library's templates:
 * invoice, eg-invoice, credit-note and delivery-note.
 *
 * Prices are before tax. A coupon shows as a discount on each line, and
 * shipping and fees are lines of their own, so the document's total matches
 * the order's.
 */
final class OrderData {

	/** The tax invoice (template "invoice"), with the ZATCA QR when the invoice type is zatca. */
	public static function invoice( WC_Order $order ): array {
		$invoice = Numbers::for( 'invoice', $order );

		return array(
			'invoice' => array(
				'number'   => $invoice['number'],
				'date'     => $invoice['date'],
				'currency' => $order->get_currency(),
				'tax_rate' => self::tax_rate( $order ),
			),
			'seller'  => self::seller(),
			'buyer'   => self::buyer( $order ),
			'items'   => self::lines( $order ),
			'qr'      => 'zatca' === Settings::get( 'invoice_type' ) ? 'zatca' : null,
		);
	}

	/** The credit note of a refund (template "credit-note"). */
	public static function credit_note( WC_Order $order, WC_Order_Refund $refund ): array {
		$note    = Numbers::for( 'credit-note', $refund );
		$invoice = Numbers::for( 'invoice', $order );

		return array(
			'type'    => 'credit',
			'note'    => array(
				'number'   => $note['number'],
				'date'     => $note['date'],
				'currency' => $order->get_currency(),
				'tax_rate' => self::refund_tax_rate( $order, $refund ),
			),
			'invoice' => array(
				'number' => $invoice['number'],
				'date'   => $invoice['date'],
			),
			'reason'  => self::refund_reason( $refund ),
			'seller'  => self::seller(),
			'buyer'   => self::buyer( $order ),
			'items'   => self::refund_lines( $refund ),
			'qr'      => 'zatca' === Settings::get( 'invoice_type' ) ? 'zatca' : null,
		);
	}

	/**
	 * The Egyptian e-invoice layout (template "eg-invoice"): an invoice for
	 * an order, or a credit note (type C) for a refund. Each line has its own
	 * VAT rate (T1).
	 */
	public static function egypt( WC_Order $order, ?WC_Order_Refund $refund = null ): array {
		$rin = trim( (string) Settings::get( 'tax_number' ) );

		if ( '' === $rin ) {
			throw new RuntimeException( __( 'The Egyptian invoice needs your tax registration number. Add it in the Easy PDF & Word settings.', 'easy-pdf-word' ) );
		}

		$number = $refund ? Numbers::for( 'credit-note', $refund ) : Numbers::for( 'invoice', $order );
		$seller = self::seller();
		$lines  = array();

		foreach ( $refund ? self::refund_lines( $refund, true ) : self::lines( $order, true ) as $line ) {
			$rate = $line['rate'];
			unset( $line['rate'] );

			if ( $rate > 0 ) {
				$line['taxes'] = array(
					array(
						'type' => 'T1',
						'rate' => $rate,
					),
				);
			}

			$lines[] = $line;
		}

		return array(
			'document' => array(
				'type'        => $refund ? 'C' : 'I',
				'internal_id' => $number['number'],
				// The local time as written: the library shows dates in PHP's time zone, which WordPress keeps at UTC.
				'issued_at'   => $number['time'],
				'currency'    => $order->get_currency(),
			),
			'issuer'   => array(
				'name'    => $seller['name'],
				'rin'     => $rin,
				'address' => $seller['address'],
			),
			'receiver' => array(
				// B for a business, P for a person.
				'type'    => '' !== $order->get_billing_company() ? 'B' : 'P',
				'name'    => self::buyer( $order )['name'],
				'address' => self::buyer( $order )['address'],
			),
			'lines'    => $lines,
		);
	}

	/** The delivery note (template "delivery-note"): what is shipped, without prices. */
	public static function delivery_note( WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$quantity = $item->get_quantity() + $order->get_qty_refunded_for_item( $item->get_id() );

			if ( $quantity <= 0 ) {
				continue;
			}

			$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;

			$items[] = array(
				'code'        => $product ? (string) $product->get_sku() : '',
				'description' => self::text( $item->get_name() ),
				'quantity'    => $quantity,
			);
		}

		if ( ! $items ) {
			throw new RuntimeException( __( 'This order has nothing left to deliver: all its items were refunded.', 'easy-pdf-word' ) );
		}

		$shipping = $order->has_shipping_address() ? 'shipping' : 'billing';
		$invoice  = Numbers::existing( 'invoice', $order );

		return array(
			'delivery' => array(
				'number'         => (string) $order->get_order_number(),
				'date'           => current_datetime()->format( 'Y-m-d' ),
				'order_number'   => (string) $order->get_order_number(),
				'invoice_number' => $invoice['number'] ?? null,
			),
			'customer' => array(
				'name'    => self::buyer( $order )['name'],
				'phone'   => $order->get_billing_phone(),
				'address' => self::address( $order, 'billing' ),
			),
			'ship_to'  => array(
				'address' => self::address( $order, $shipping ),
				'contact' => self::text( 'shipping' === $shipping ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name() ),
				'phone'   => 'shipping' === $shipping && $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone(),
			),
			'items'    => $items,
		);
	}

	/** The shop: the company settings, else the site name and the store address. */
	public static function seller(): array {
		return array_map( array( self::class, 'text' ), Settings::company() );
	}

	/** The customer: the billing company or name, and the billing address. */
	public static function buyer( WC_Order $order ): array {
		$name = $order->get_billing_company();
		$name = '' !== $name ? $name : $order->get_formatted_billing_full_name();
		$name = '' !== trim( $name ) ? $name : $order->get_billing_email();

		return array(
			'name'    => self::text( '' !== trim( $name ) ? $name : __( 'Customer', 'easy-pdf-word' ) ),
			'address' => self::address( $order, 'billing' ),
		);
	}

	/**
	 * The order's products, shipping and fees, before tax.
	 *
	 * @param bool $with_rates Add each line's VAT rate as "rate".
	 */
	public static function lines( WC_Order $order, bool $with_rates = false ): array {
		$lines = array();

		foreach ( $order->get_items() as $item ) {
			$quantity = (float) $item->get_quantity();

			if ( $quantity <= 0 ) {
				continue;
			}

			$subtotal = (float) $item->get_subtotal();
			$total    = (float) $item->get_total();

			$lines[] = self::line(
				$item,
				array(
					'description' => self::text( $item->get_name() ),
					'quantity'    => $quantity,
					'unit_price'  => $subtotal / $quantity,
					'discount'    => max( 0.0, $subtotal - $total ),
				),
				$with_rates
			);
		}

		$credit = 0.0;

		foreach ( $order->get_items( array( 'shipping', 'fee' ) ) as $item ) {
			$total = (float) $item->get_total();

			// A negative fee is a discount: it comes off the other lines below.
			if ( $total < 0 ) {
				$credit -= $total;
				continue;
			}

			$lines[] = self::line(
				$item,
				array(
					'description' => self::text( $item->get_name() ),
					'quantity'    => 1,
					'unit_price'  => $total,
				),
				$with_rates
			);
		}

		return self::apply_credit( $lines, $credit );
	}

	/**
	 * The refunded lines. A refund of an amount with no items is one line
	 * with the refund's reason.
	 */
	public static function refund_lines( WC_Order_Refund $refund, bool $with_rates = false ): array {
		$lines = array();

		foreach ( $refund->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
			$total = abs( (float) $item->get_total() );

			if ( $total <= 0 ) {
				continue;
			}

			$quantity = abs( (float) ( is_callable( array( $item, 'get_quantity' ) ) ? $item->get_quantity() : 1 ) );
			$quantity = $quantity > 0 ? $quantity : 1;
			$subtotal = is_callable( array( $item, 'get_subtotal' ) ) ? abs( (float) $item->get_subtotal() ) : $total;
			$subtotal = max( $subtotal, $total );

			$lines[] = self::line(
				$item,
				array(
					'description' => self::text( $item->get_name() ),
					'quantity'    => $quantity,
					'unit_price'  => $subtotal / $quantity,
					'discount'    => $subtotal - $total,
				),
				$with_rates
			);
		}

		if ( $lines ) {
			return $lines;
		}

		$amount = abs( (float) $refund->get_amount() ) - abs( (float) $refund->get_total_tax() );
		$line   = array(
			'description' => self::refund_reason( $refund ),
			'quantity'    => 1,
			'unit_price'  => max( 0.0, $amount ),
		);

		if ( $with_rates ) {
			$line['rate'] = $amount > 0 ? round( abs( (float) $refund->get_total_tax() ) / $amount * 100, 2 ) : 0.0;
		}

		return array( $line );
	}

	/**
	 * The invoice's VAT rate. The invoice template has one rate: the order's
	 * rate when it has one, 0 when it has no tax, and the average rate when
	 * its lines have different rates.
	 */
	public static function tax_rate( WC_Abstract_Order $order ): float {
		$rates = array();

		foreach ( $order->get_items( 'tax' ) as $tax ) {
			if ( abs( (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total() ) > 0.000001 && null !== $tax->get_rate_percent() ) {
				$rates[] = (float) $tax->get_rate_percent();
			}
		}

		$rates = array_values( array_unique( $rates ) );

		if ( 1 === count( $rates ) ) {
			return $rates[0];
		}

		$taxable = abs( (float) $order->get_total() ) - abs( (float) $order->get_total_tax() );

		return $taxable > 0 ? round( abs( (float) $order->get_total_tax() ) / $taxable * 100, 2 ) : 0.0;
	}

	private static function refund_tax_rate( WC_Order $order, WC_Order_Refund $refund ): float {
		return 0.0 !== (float) $refund->get_total_tax() ? self::tax_rate( $refund ) : self::tax_rate( $order );
	}

	private static function refund_reason( WC_Order_Refund $refund ): string {
		$reason = self::text( (string) $refund->get_reason() );

		return '' !== $reason ? $reason : __( 'Refund', 'easy-pdf-word' );
	}

	/** Adds the line's VAT rate when asked: its tax over its total. */
	private static function line( WC_Order_Item $item, array $line, bool $with_rates ): array {
		if ( $with_rates ) {
			$total        = abs( (float) $item->get_total() );
			$line['rate'] = $total > 0 ? round( abs( (float) $item->get_total_tax() ) / $total * 100, 2 ) : 0.0;
		}

		return $line;
	}

	/** Takes a negative fee off the lines, in order, as discounts. */
	private static function apply_credit( array $lines, float $credit ): array {
		foreach ( $lines as $i => $line ) {
			if ( $credit <= 0 ) {
				break;
			}

			$room = $line['quantity'] * $line['unit_price'] - ( $line['discount'] ?? 0 );
			$take = min( $room, $credit );

			$lines[ $i ]['discount'] = ( $line['discount'] ?? 0 ) + $take;
			$credit                 -= $take;
		}

		return $lines;
	}

	/** An address on one line, without the name and company. */
	private static function address( WC_Order $order, string $type ): string {
		$address = $order->get_address( $type );
		unset( $address['first_name'], $address['last_name'], $address['company'], $address['email'], $address['phone'] );

		return self::text( WC()->countries->get_formatted_address( $address, self::separator() ) );
	}

	private static function separator(): string {
		return str_starts_with( get_locale(), 'ar' ) ? '، ' : ', ';
	}

	/** Plain text: WooCommerce and WordPress return some values escaped for HTML; the templates escape again. */
	private static function text( string $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
}
