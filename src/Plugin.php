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

		Admin\SettingsPage::register();
		WooCommerce\WooCommerce::boot();
	}

	/** The Arabic translation ships with the plugin; others come from translate.wordpress.org. */
	public static function load_translations(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- bundled translations.
		load_plugin_textdomain( 'easy-pdf-word', false, dirname( plugin_basename( EASY_PDF_WORD_FILE ) ) . '/languages' );
	}
}
