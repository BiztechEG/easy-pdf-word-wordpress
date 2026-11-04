<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * The customer's documents in My account: an invoice button next to each
 * order, and the order's documents under its details.
 */
final class MyAccount {

	public static function register(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( self::class, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( self::class, 'order_documents' ) );
	}

	/**
	 * @param array    $actions The order's buttons in My account > Orders.
	 * @param WC_Order $order   The order.
	 */
	public static function order_actions( $actions, $order ): array {
		$actions = is_array( $actions ) ? $actions : array();

		if ( $order instanceof WC_Order && Downloads::customer_can( 'invoice', $order, get_current_user_id() ) ) {
			$actions['easy-pdf-word-invoice'] = array(
				'url'  => Downloads::url( 'invoice', $order ),
				'name' => __( 'Invoice', 'easy-pdf-word' ),
			);
		}

		return $actions;
	}

	/** The documents under the order's details, for the customer who placed it. */
	public static function order_documents( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$user  = get_current_user_id();
		$links = array();

		foreach ( array( 'invoice', 'delivery-note' ) as $type ) {
			if ( Downloads::customer_can( $type, $order, $user ) ) {
				$links[] = array( OrderDocument::label( $type ), Downloads::url( $type, $order ) );
			}
		}

		if ( Downloads::customer_can( 'credit-note', $order, $user ) ) {
			foreach ( $order->get_refunds() as $refund ) {
				$links[] = array( OrderDocument::label( 'credit-note' ), Downloads::url( 'credit-note', $order, $refund ) );
			}
		}

		if ( ! $links ) {
			return;
		}

		echo '<section class="easy-pdf-word-documents"><h2>' . esc_html__( 'Documents', 'easy-pdf-word' ) . '</h2><p>';

		foreach ( $links as [ $label, $url ] ) {
			printf( '<a class="woocommerce-button button" href="%1$s">%2$s</a> ', esc_url( $url ), esc_html( $label ) );
		}

		echo '</p></section>';
	}
}
