<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use BiztechEG\EasyPdfWordWP\Documents;
use BiztechEG\EasyPdfWordWP\Settings;
use Throwable;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Attaches an order's documents to WooCommerce emails, as the attach setting
 * lists them: by default the invoice to "Completed order" and "Order details",
 * and the credit note of the latest refund to "Refunded order".
 */
final class Emails {

	/** @var list<string> Files to remove when the request ends. */
	private static array $files = array();

	public static function register(): void {
		add_filter( 'woocommerce_email_attachments', array( self::class, 'attach' ), 10, 3 );
	}

	/**
	 * @param array  $attachments File paths.
	 * @param string $email_id    The WooCommerce email, such as customer_completed_order.
	 * @param mixed  $order       The email's object: an order for order emails.
	 */
	public static function attach( $attachments, $email_id, $order ): array {
		$attachments = is_array( $attachments ) ? $attachments : array();

		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}

		foreach ( (array) Settings::get( 'attach' ) as $type => $emails ) {
			if ( ! in_array( $type, OrderDocument::TYPES, true ) || ! in_array( $email_id, (array) $emails, true ) ) {
				continue;
			}

			// No tax invoice for an order that is not paid yet, such as a payment request.
			if ( 'invoice' === $type && ! $order->has_status( (array) Settings::get( 'invoice_statuses' ) ) ) {
				continue;
			}

			$refund = null;

			if ( 'credit-note' === $type ) {
				$refund = $order->get_refunds()[0] ?? null;

				if ( ! $refund ) {
					continue;
				}
			}

			$path = self::save( $type, $order, $refund );

			if ( null !== $path ) {
				$attachments[] = $path;
			}
		}

		return $attachments;
	}

	/** The PDF in a folder of its own in the temp folder, so its name stays clean; null if it could not be made. */
	private static function save( string $type, WC_Order $order, $refund ): ?string {
		try {
			$file = OrderDocument::file( $type, $order, $refund );
			$dir  = Documents::config()['temp_dir'] . '/mail-' . wp_generate_password( 12, false );
			$path = $file->save( $dir . '/' . $file->filename() );
		} catch ( Throwable $e ) {
			// The email still goes out, without the document.
			/* translators: 1: the document, such as Invoice, 2: the order number, 3: the reason. */
			do_action( 'easy_pdf_word_warning', sprintf( __( '%1$s of order %2$s was not attached: %3$s', 'easy-pdf-word' ), OrderDocument::label( $type ), $order->get_order_number(), $e->getMessage() ) );

			return null;
		}

		if ( ! self::$files ) {
			add_action( 'shutdown', array( self::class, 'clean_up' ) );
		}

		self::$files[] = $path;

		return $path;
	}

	/** Removes the attached files and their folders. */
	public static function clean_up(): void {
		foreach ( self::$files as $path ) {
			wp_delete_file( $path );
			@rmdir( dirname( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- an empty temp folder.
		}

		self::$files = array();
	}
}
