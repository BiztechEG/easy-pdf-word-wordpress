<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use Throwable;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * The documents on the order screens: a box on the order page, an invoice
 * button in the orders list, and a bulk action that downloads the invoices
 * of the selected orders as one ZIP file.
 */
final class AdminOrders {

	public const BULK_ACTION = 'easy_pdf_word_invoices';

	/** Orders in one ZIP file; more would risk the server's time limit. */
	private const BULK_LIMIT = 50;

	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_box' ) );
		add_filter( 'woocommerce_admin_order_actions', array( self::class, 'list_action' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'styles' ) );

		// The orders list with WooCommerce's order tables, and with posts.
		foreach ( array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order' ) as $screen ) {
			add_filter( "bulk_actions-{$screen}", array( self::class, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-{$screen}", array( self::class, 'handle_bulk_action' ), 10, 3 );
		}
	}

	public static function add_box(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		add_meta_box(
			'easy-pdf-word-documents',
			__( 'Documents', 'easy-pdf-word' ),
			array( self::class, 'render_box' ),
			function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * The box's content.
	 *
	 * @param WC_Order|WP_Post $order_or_post The order, or its post without WooCommerce's order tables.
	 */
	public static function render_box( $order_or_post ): void {
		$order = $order_or_post instanceof WC_Order ? $order_or_post : wc_get_order( $order_or_post instanceof WP_Post ? $order_or_post->ID : 0 );

		if ( ! $order instanceof WC_Order || ! $order->get_id() ) {
			echo '<p>' . esc_html__( 'Save the order first.', 'easy-pdf-word' ) . '</p>';
			return;
		}

		$invoice = Numbers::existing( 'invoice', $order );

		echo '<ul class="easy-pdf-word-documents">';
		self::render_row( 'invoice', $order, null, $invoice ? $invoice['number'] : __( 'Gets its number when first opened', 'easy-pdf-word' ) );
		self::render_row( 'delivery-note', $order );

		foreach ( $order->get_refunds() as $refund ) {
			$note = Numbers::existing( 'credit-note', $refund );
			/* translators: %s: the refund's ID. */
			self::render_row( 'credit-note', $order, $refund, $note ? $note['number'] : sprintf( __( 'Refund #%s', 'easy-pdf-word' ), $refund->get_id() ) );
		}

		echo '</ul>';
	}

	private static function render_row( string $type, WC_Order $order, $refund = null, string $detail = '' ): void {
		echo '<li><strong>' . esc_html( OrderDocument::label( $type ) ) . '</strong>';

		if ( '' !== $detail ) {
			echo ' <span class="description">' . esc_html( $detail ) . '</span>';
		}

		echo '<br>';

		printf(
			'<a class="button button-small" href="%1$s" target="_blank" rel="noopener">PDF</a> <a class="button button-small" href="%2$s">Word</a>',
			esc_url( Downloads::url( $type, $order, $refund ) ),
			esc_url( Downloads::url( $type, $order, $refund, 'docx' ) )
		);

		echo '</li>';
	}

	/**
	 * An invoice button next to each order in the orders list.
	 *
	 * @param array    $actions The row's buttons.
	 * @param WC_Order $order   The order.
	 */
	public static function list_action( $actions, $order ): array {
		$actions = is_array( $actions ) ? $actions : array();

		if ( $order instanceof WC_Order && current_user_can( 'edit_shop_orders' ) ) {
			$actions['easy_pdf_word_invoice'] = array(
				'url'    => Downloads::url( 'invoice', $order ),
				'name'   => __( 'Invoice (PDF)', 'easy-pdf-word' ),
				'action' => 'easy-pdf-word-invoice',
			);
		}

		return $actions;
	}

	/** The invoice button's icon in the orders list. */
	public static function styles(): void {
		if ( wp_style_is( 'woocommerce_admin_styles', 'enqueued' ) ) {
			wp_add_inline_style(
				'woocommerce_admin_styles',
				'.wc-action-button-easy-pdf-word-invoice::after{font-family:dashicons!important;content:"\f491"!important}'
				. '.easy-pdf-word-documents li{margin-bottom:10px}'
			);
		}
	}

	public static function bulk_actions( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();

		if ( current_user_can( 'edit_shop_orders' ) ) {
			$actions[ self::BULK_ACTION ] = __( 'Download invoices (ZIP)', 'easy-pdf-word' );
		}

		return $actions;
	}

	/**
	 * Sends the invoices of the selected orders as one ZIP file. WordPress
	 * and WooCommerce check the list's nonce before this runs.
	 *
	 * @param string $redirect Where to go after the action.
	 * @param string $action   The bulk action.
	 * @param array  $ids      The selected orders.
	 */
	public static function handle_bulk_action( $redirect, $action, $ids ): string {
		if ( self::BULK_ACTION !== $action || ! current_user_can( 'edit_shop_orders' ) ) {
			return (string) $redirect;
		}

		$files = self::invoices( array_slice( array_map( 'absint', (array) $ids ), 0, self::BULK_LIMIT ) );

		if ( ! $files ) {
			wp_die( esc_html__( 'None of the selected orders has an invoice.', 'easy-pdf-word' ), '', array( 'response' => 404 ) );
		}

		easy_pdf_word()->zip( $files, 'invoices-' . current_datetime()->format( 'Y-m-d' ) . '.zip' )->send();
		exit;
	}

	/**
	 * The invoices of the orders, by file name. An order whose invoice cannot
	 * be made is left out with a warning.
	 *
	 * @param int[] $ids Order IDs.
	 * @return array<string, \BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\Output\File>
	 */
	public static function invoices( array $ids ): array {
		$files = array();

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );

			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			try {
				$file                       = OrderDocument::file( 'invoice', $order );
				$files[ $file->filename() ] = $file;
			} catch ( Throwable $e ) {
				/* translators: 1: the order number, 2: the reason. */
				do_action( 'easy_pdf_word_warning', sprintf( __( 'The invoice of order %1$s was left out: %2$s', 'easy-pdf-word' ), $order->get_order_number(), wp_specialchars_decode( $e->getMessage(), ENT_QUOTES ) ) );
			}
		}

		return $files;
	}
}
