<?php
/**
 * Plugin Name:       Easy PDF & Word
 * Plugin URI:        https://easypdf.biztech-eg.com/guide/wordpress-plugin
 * Description:       PDF and Word documents in any language, with first-class Arabic: invoices, tax invoices, credit notes and delivery notes for WooCommerce, and a PHP API for your own documents.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            BizTech
 * Author URI:        https://github.com/BiztechEG
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-pdf-word
 * Domain Path:       /languages
 * WC requires at least: 11.1
 * WC tested up to:   11.2
 */

defined( 'ABSPATH' ) || exit;

define( 'EASY_PDF_WORD_VERSION', '1.0.0' );
define( 'EASY_PDF_WORD_FILE', __FILE__ );

// Checked before any PHP 8.2 code is loaded, so older PHP gets a notice instead of a fatal error.
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Easy PDF & Word needs PHP 8.2 or newer. Ask your host to update PHP.', 'easy-pdf-word' );
			echo '</p></div>';
		}
	);

	return;
}

require __DIR__ . '/src/bootstrap.php';
