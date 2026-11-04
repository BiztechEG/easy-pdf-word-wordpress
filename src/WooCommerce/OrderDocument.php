<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use BiztechEG\EasyPdfWordWP\Settings;
use BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\Output\File;
use InvalidArgumentException;
use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * The documents of an order: its invoice, a credit note for each refund and
 * its delivery note, as PDF or Word files.
 */
final class OrderDocument {

	public const TYPES = array( 'invoice', 'credit-note', 'delivery-note' );

	public const FORMATS = array( 'pdf', 'docx' );

	/** The document's name, in the site language. */
	public static function label( string $type ): string {
		return match ( $type ) {
			'invoice'       => __( 'Invoice', 'easy-pdf-word' ),
			'credit-note'   => __( 'Credit note', 'easy-pdf-word' ),
			'delivery-note' => __( 'Delivery note', 'easy-pdf-word' ),
			default         => $type,
		};
	}

	/**
	 * The document, ready for ->pdf() or ->word().
	 *
	 * @param string               $type   "invoice", "credit-note" or "delivery-note".
	 * @param WC_Order             $order  The order.
	 * @param WC_Order_Refund|null $refund The refund, for a credit note.
	 */
	public static function make( string $type, WC_Order $order, ?WC_Order_Refund $refund = null ): Document {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new InvalidArgumentException( esc_html( "Unknown document [{$type}]." ) );
		}

		if ( 'credit-note' === $type && ( ! $refund || $refund->get_parent_id() !== $order->get_id() ) ) {
			throw new InvalidArgumentException( 'A credit note needs a refund of the order.' );
		}

		$egypt = 'egypt' === Settings::get( 'invoice_type' ) && 'delivery-note' !== $type;

		[ $template, $data ] = match ( true ) {
			$egypt                  => array( 'eg-invoice', OrderData::egypt( $order, 'credit-note' === $type ? $refund : null ) ),
			'invoice' === $type     => array( 'invoice', OrderData::invoice( $order ) ),
			'credit-note' === $type => array( 'credit-note', OrderData::credit_note( $order, $refund ) ),
			default                 => array( 'delivery-note', OrderData::delivery_note( $order ) ),
		};

		/**
		 * Filters the template of an order's document, for example to use your own.
		 *
		 * @param string               $template The template name.
		 * @param string               $type     "invoice", "credit-note" or "delivery-note".
		 * @param WC_Order             $order    The order.
		 * @param WC_Order_Refund|null $refund   The refund, for a credit note.
		 */
		$template = (string) apply_filters( 'easy_pdf_word_woocommerce_template', $template, $type, $order, $refund );

		/**
		 * Filters the data of an order's document.
		 *
		 * @param array                $data     The template's data.
		 * @param string               $type     "invoice", "credit-note" or "delivery-note".
		 * @param WC_Order             $order    The order.
		 * @param WC_Order_Refund|null $refund   The refund, for a credit note.
		 * @param string               $template The template name.
		 */
		$data = (array) apply_filters( 'easy_pdf_word_woocommerce_data', $data, $type, $order, $refund, $template );

		return easy_pdf_word()->template( $template, $data );
	}

	/** The document as a PDF or Word file, with its file name. */
	public static function file( string $type, WC_Order $order, ?WC_Order_Refund $refund = null, string $format = 'pdf' ): File {
		$document = self::make( $type, $order, $refund );
		$filename = self::filename( $type, $order, $refund, $format );

		return 'docx' === $format ? $document->word( $filename ) : $document->pdf( $filename );
	}

	/** The file name, such as invoice-INV-0001.pdf: the document and its number, safe for a file name. */
	public static function filename( string $type, WC_Order $order, ?WC_Order_Refund $refund = null, string $format = 'pdf' ): string {
		$number = match ( $type ) {
			'invoice'     => Numbers::existing( 'invoice', $order )['number'] ?? $order->get_order_number(),
			'credit-note' => $refund ? ( Numbers::existing( 'credit-note', $refund )['number'] ?? $refund->get_id() ) : $order->get_order_number(),
			default       => $order->get_order_number(),
		};

		$name = sanitize_file_name( $type . '-' . $number . '.' . ( 'docx' === $format ? 'docx' : 'pdf' ) );

		/**
		 * Filters the file name of an order's document.
		 *
		 * @param string               $name   The file name.
		 * @param string               $type   "invoice", "credit-note" or "delivery-note".
		 * @param WC_Order             $order  The order.
		 * @param WC_Order_Refund|null $refund The refund, for a credit note.
		 */
		return (string) apply_filters( 'easy_pdf_word_woocommerce_filename', $name, $type, $order, $refund );
	}
}
