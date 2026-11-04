<?php
/**
 * The plugin's public PHP functions.
 */

defined( 'ABSPATH' ) || exit;

use BiztechEG\EasyPdfWordWP\Documents;

if ( ! function_exists( 'easy_pdf_word' ) ) {
	/**
	 * The document factory, set up from the plugin's settings.
	 *
	 * For example: easy_pdf_word()->template( 'invoice', $data )->pdf( 'invoice.pdf' )->send();
	 *
	 * The library is bundled under the prefix BiztechEG\EasyPdfWordWP\Vendor\, so the
	 * factory is a BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\DocumentFactory.
	 * It has the same methods as the library's; see https://easypdf.biztech-eg.com.
	 *
	 * @return \BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\DocumentFactory
	 */
	function easy_pdf_word() {
		return Documents::factory();
	}
}
