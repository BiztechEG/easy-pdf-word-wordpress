<?php

namespace BiztechEG\EasyPdfWordWP\Tests\WooCommerce;

use BiztechEG\EasyPdfWordWP\Documents;
use BiztechEG\EasyPdfWordWP\Settings;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use WC_Product_Simple;
use WC_Tax;
use WP_UnitTestCase;

/**
 * A Saudi shop with 15% VAT on products and shipping, and helpers to make
 * orders. Needs WooCommerce: set WC_DIR to its folder.
 */
abstract class TestCase extends WP_UnitTestCase {

	protected int $customer;

	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'WC' ) ) {
			$this->markTestSkipped( 'Set WC_DIR to a WooCommerce folder to run the WooCommerce tests.' );
		}

		Documents::reset();
		delete_option( Settings::OPTION );

		update_option( 'blogname', 'Noor Store' );
		update_option( 'woocommerce_default_country', 'SA' );
		update_option( 'woocommerce_store_address', 'King Fahd Road' );
		update_option( 'woocommerce_store_city', 'Riyadh' );
		update_option( 'woocommerce_currency', 'SAR' );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate'          => '15.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 1,
				'tax_rate_class'    => '',
			)
		);

		$this->customer = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	public function tear_down(): void {
		Documents::reset();
		parent::tear_down();
	}

	protected function settings( array $settings ): void {
		update_option( Settings::OPTION, $settings );
	}

	protected function product( float $price = 100, string $name = 'استشارة' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( (string) $price );
		$product->set_sku( 'SKU-' . wp_generate_password( 4, false ) );
		$product->save();

		return $product;
	}

	/**
	 * A processing order of the customer: 2 × 100 and shipping of 20, with 15% VAT.
	 *
	 * @param array $options coupon (fixed amount off the cart), fee (an amount, negative for a discount), status.
	 */
	protected function order( array $options = array() ): WC_Order {
		$order = wc_create_order( array( 'customer_id' => $this->customer ) );
		$order->add_product( $this->product(), 2 );
		$order->set_address(
			array(
				'first_name' => 'عمرو',
				'last_name'  => 'محمد',
				'company'    => $options['company'] ?? '',
				'address_1'  => 'شارع التحلية',
				'city'       => 'جدة',
				'country'    => 'SA',
				'email'      => 'amr@example.org',
				'phone'      => '0500000000',
			),
			'billing'
		);

		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Flat rate' );
		$shipping->set_total( '20' );
		$order->add_item( $shipping );

		if ( isset( $options['fee'] ) ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( 'Fee' );
			$fee->set_amount( (string) $options['fee'] );
			$fee->set_total( (string) $options['fee'] );
			$order->add_item( $fee );
		}

		$order->calculate_totals();

		if ( isset( $options['coupon'] ) ) {
			$coupon = new WC_Coupon();
			$coupon->set_code( 'off' . wp_generate_password( 4, false, false ) );
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( (string) $options['coupon'] );
			$coupon->save();
			$order->apply_coupon( $coupon->get_code() );
		}

		$order->set_status( $options['status'] ?? 'processing' );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/** The total of invoice data: each line before tax, plus the invoice's VAT rate. */
	protected static function total( array $items, float $rate ): float {
		$taxable = 0.0;

		foreach ( $items as $item ) {
			$taxable += round( $item['quantity'] * $item['unit_price'], 2 ) - round( $item['discount'] ?? 0, 2 );
		}

		return round( $taxable + round( $taxable * $rate / 100, 2 ), 2 );
	}
}
