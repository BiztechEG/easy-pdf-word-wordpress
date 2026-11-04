<?php

namespace BiztechEG\EasyPdfWordWP;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's hooks.
 */
final class Plugin {

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'init', array( self::class, 'load_translations' ) );
		// Settings or the site language may change during a request.
		add_action( 'switch_locale', array( Documents::class, 'reset' ) );
		add_action( 'restore_previous_locale', array( Documents::class, 'reset' ) );
		// Every warning, from the library or the plugin, reaches the PHP error log with WP_DEBUG.
		add_action( 'easy_pdf_word_warning', array( self::class, 'log_warning' ) );

		Admin\SettingsPage::register();
		WooCommerce\WooCommerce::boot();
	}

	/** Writes a warning to the PHP error log when WP_DEBUG is on: for developers, not visitors. */
	public static function log_warning( $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_string( $message ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only with WP_DEBUG.
			error_log( 'Easy PDF & Word: ' . $message );
		}
	}

	/** The Arabic translation ships with the plugin; others come from translate.wordpress.org. */
	public static function load_translations(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- bundled translations.
		load_plugin_textdomain( 'easy-pdf-word', false, dirname( plugin_basename( EASY_PDF_WORD_FILE ) ) . '/languages' );
	}
}
