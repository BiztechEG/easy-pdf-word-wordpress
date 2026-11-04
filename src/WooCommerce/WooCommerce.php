<?php

namespace BiztechEG\EasyPdfWordWP\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Turns on the WooCommerce documents when WooCommerce is active.
 */
final class WooCommerce {

	public static function boot(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare_compatibility' ) );
		// After WooCommerce has loaded, whatever the order of the plugins.
		add_action( 'plugins_loaded', array( self::class, 'register' ), 20 );
	}

	/** Works with WooCommerce's order tables (HPOS). */
	public static function declare_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EASY_PDF_WORD_FILE, true );
		}
	}

	public static function register(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		Downloads::register();
		Emails::register();
		AdminOrders::register();
		MyAccount::register();
	}
}
