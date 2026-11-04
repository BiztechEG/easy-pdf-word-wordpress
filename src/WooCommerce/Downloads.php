<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use BiztechEG\EasyPdfWordWP\Settings;
use BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\Output\File;
use Throwable;
use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Download links for an order's documents. A link carries a nonce for that
 * document of that order, and the download checks that the visitor may see
 * it: shop managers any order, a customer only their own order's invoice and
 * credit notes, once the order is paid.
 */
final class Downloads {

	public const ACTION = 'easy_pdf_word_document';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		// Visitors who are not logged in (or whose session ended) go to the login form.
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'to_login' ) );
	}

	/** Sends a visitor who is not logged in to My account, where they can log in. */
	public static function to_login(): void {
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/** The download link of a document for the current user (its nonce is theirs). */
	public static function url( string $type, WC_Order $order, ?WC_Order_Refund $refund = null, string $format = 'pdf' ): string {
		$args = array(
			'action'   => self::ACTION,
			'document' => $type,
			'order'    => $order->get_id(),
			'format'   => $format,
		);

		if ( $refund ) {
			$args['refund'] = $refund->get_id();
		}

		$args['_wpnonce'] = wp_create_nonce( self::nonce_action( $type, $order, $refund ) );

		// Not escaped: escape it where it is printed.
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/** Shop managers can download any document; a customer, see customer_can(). */
	public static function can_download( string $type, WC_Order $order, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, 'edit_shop_orders' ) ) {
			return true;
		}

		return self::customer_can( $type, $order, $user_id );
	}

	/**
	 * A customer can download the documents the customer_documents setting
	 * lists, of their own order, and the invoice only in the statuses of the
	 * invoice_statuses setting (paid orders, by default).
	 */
	public static function customer_can( string $type, WC_Order $order, int $user_id ): bool {
		$allowed = $user_id > 0
			&& $order->get_customer_id() === $user_id
			&& in_array( $type, (array) Settings::get( 'customer_documents' ), true )
			&& ( 'invoice' !== $type || $order->has_status( (array) Settings::get( 'invoice_statuses' ) ) );

		/**
		 * Filters whether a customer can download a document of an order.
		 *
		 * @param bool     $allowed Whether they can.
		 * @param string   $type    "invoice", "credit-note" or "delivery-note".
		 * @param WC_Order $order   The order.
		 * @param int      $user_id The customer.
		 */
		return (bool) apply_filters( 'easy_pdf_word_customer_can_download', $allowed, $type, $order, $user_id );
	}

	/** admin-post.php?action=easy_pdf_word_document&document=invoice&order=123&_wpnonce=... */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request() checks the nonce once the order is known.
		self::request( wp_unslash( $_GET ) )->send();
		exit;
	}

	/**
	 * The file a download link asks for, after checking the link and the
	 * visitor; wp_die() with 404 or 403 otherwise.
	 *
	 * @param array $query The link's query string, unslashed.
	 */
	public static function request( array $query ): File {
		// Only plain values: document[]=... is not a document.
		$value = static fn ( string $key ): string => isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) ? (string) $query[ $key ] : '';

		$type   = sanitize_key( $value( 'document' ) );
		$format = sanitize_key( '' !== $value( 'format' ) ? $value( 'format' ) : 'pdf' );
		$order  = wc_get_order( absint( $value( 'order' ) ) );
		$refund = isset( $query['refund'] ) ? wc_get_order( absint( $value( 'refund' ) ) ) : null;
		$nonce  = sanitize_text_field( $value( '_wpnonce' ) );

		if ( ! $order instanceof WC_Order || ! in_array( $type, OrderDocument::TYPES, true ) || ! in_array( $format, OrderDocument::FORMATS, true ) ) {
			wp_die( esc_html__( 'This document was not found.', 'easy-pdf-word' ), '', array( 'response' => 404 ) );
		}

		if ( null !== $refund && ( ! $refund instanceof WC_Order_Refund || $refund->get_parent_id() !== $order->get_id() ) ) {
			wp_die( esc_html__( 'This document was not found.', 'easy-pdf-word' ), '', array( 'response' => 404 ) );
		}

		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $type, $order, $refund ) ) ) {
			wp_die( esc_html__( 'This link has expired. Go back and open the document again.', 'easy-pdf-word' ), '', array( 'response' => 403 ) );
		}

		if ( ! self::can_download( $type, $order ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to download this document.', 'easy-pdf-word' ), '', array( 'response' => 403 ) );
		}

		try {
			return OrderDocument::file( $type, $order, $refund, $format );
		} catch ( Throwable $e ) {
			// Shop managers see why (a missing setting, an invalid order); customers do not.
			$message = current_user_can( 'edit_shop_orders' )
				? $e->getMessage()
				: __( 'The document could not be made. Please contact the shop.', 'easy-pdf-word' );

			wp_die( esc_html( $message ), '', array( 'response' => 500 ) );
		}
	}

	private static function nonce_action( string $type, WC_Order $order, ?WC_Order_Refund $refund ): string {
		return 'easy_pdf_word_' . $type . '_' . $order->get_id() . ( $refund ? '_' . $refund->get_id() : '' );
	}
}
