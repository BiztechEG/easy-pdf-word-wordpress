<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

use BiztechEG\EasyPdfWordWP\Settings;
use WC_Abstract_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Sequential document numbers: an invoice number for an order and a credit
 * note number for a refund, given the first time the document is made and
 * kept in the order's meta, so the document keeps its number and date.
 */
final class Numbers {

	/** The sequences and the setting that holds each prefix. */
	private const PREFIXES = array(
		'invoice'     => 'invoice_prefix',
		'credit-note' => 'credit_note_prefix',
	);

	/**
	 * The document's number and date, given now if it has none yet.
	 *
	 * @param string            $sequence "invoice" for an order, "credit-note" for a refund.
	 * @param WC_Abstract_Order $order    The order or the refund.
	 * @return array{number: string, date: string, time: string}
	 */
	public static function for( string $sequence, WC_Abstract_Order $order ): array {
		$existing = self::existing( $sequence, $order );

		if ( $existing ) {
			return $existing;
		}

		// Two requests at once (a double click, an email while downloading) give one number.
		$lock = 'easy_pdf_word_lock_' . $sequence . '_' . $order->get_id();

		$locked = false;

		for ( $tries = 0; $tries < 20; $tries++ ) {
			if ( add_option( $lock, time(), '', false ) ) {
				$locked = true;
				break;
			}

			// A lock left by a request that died.
			if ( (int) get_option( $lock ) < time() - 30 ) {
				delete_option( $lock );
				continue;
			}

			usleep( 100000 );
			$existing = self::existing( $sequence, $order, true );

			if ( $existing ) {
				return $existing;
			}
		}

		try {
			$existing = self::existing( $sequence, $order, true );

			if ( $existing ) {
				return $existing;
			}

			$number = self::format( $sequence, self::next( $sequence ) );
			// The site's local date and time.
			$date = current_datetime()->format( 'Y-m-d H:i:s' );

			$order->update_meta_data( self::meta( $sequence, 'number' ), $number );
			$order->update_meta_data( self::meta( $sequence, 'date' ), $date );
			$order->save_meta_data();
		} finally {
			if ( $locked ) {
				delete_option( $lock );
			}
		}

		return self::result( $number, $date );
	}

	/**
	 * The document's number and date if it has them.
	 *
	 * @return array{number: string, date: string, time: string}|null
	 */
	public static function existing( string $sequence, WC_Abstract_Order $order, bool $fresh = false ): ?array {
		if ( $fresh ) {
			$order->read_meta_data( true );
		}

		$number = (string) $order->get_meta( self::meta( $sequence, 'number' ) );

		if ( '' === $number ) {
			return null;
		}

		return self::result( $number, (string) $order->get_meta( self::meta( $sequence, 'date' ) ) );
	}

	/** date: 2026-11-04, time: 2026-11-04 18:30:00, in the site's time zone. */
	private static function result( string $number, string $datetime ): array {
		return array(
			'number' => $number,
			'date'   => substr( $datetime, 0, 10 ),
			'time'   => strlen( $datetime ) > 10 ? $datetime : $datetime . ' 00:00:00',
		);
	}

	/** The next number of a sequence: 1, 2, 3 ... */
	public static function next( string $sequence ): int {
		global $wpdb;

		$option = self::sequence_option( $sequence );
		add_option( $option, 0, '', false );

		// One statement, so two requests at once never get the same number.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an atomic counter; the option cache is cleared below.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", $option ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$number = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		wp_cache_delete( $option, 'options' );

		return $number;
	}

	/** The number the sequence gives next. */
	public static function peek( string $sequence ): int {
		return (int) get_option( self::sequence_option( $sequence ), 0 ) + 1;
	}

	/** Start a sequence again from $number, for example at the start of a year. */
	public static function restart( string $sequence, int $number ): void {
		update_option( self::sequence_option( $sequence ), max( 0, $number - 1 ), false );
	}

	/** INV-0001: the prefix setting and the number with the padding setting. */
	public static function format( string $sequence, int $number ): string {
		$prefix  = (string) Settings::get( self::PREFIXES[ $sequence ] ?? '' );
		$padding = max( 1, min( 12, (int) Settings::get( 'number_padding' ) ) );
		$prefix  = str_replace( '{year}', current_datetime()->format( 'Y' ), $prefix );

		$formatted = $prefix . str_pad( (string) $number, $padding, '0', STR_PAD_LEFT );

		/**
		 * Filters a new document number.
		 *
		 * @param string $formatted The number with its prefix.
		 * @param string $sequence  "invoice" or "credit-note".
		 * @param int    $number    The sequential number.
		 */
		return (string) apply_filters( 'easy_pdf_word_document_number', $formatted, $sequence, $number );
	}

	private static function meta( string $sequence, string $field ): string {
		return '_easy_pdf_word_' . str_replace( '-', '_', $sequence ) . '_' . $field;
	}

	private static function sequence_option( string $sequence ): string {
		return 'easy_pdf_word_next_' . str_replace( '-', '_', $sequence );
	}
}
