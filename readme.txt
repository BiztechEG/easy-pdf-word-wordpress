=== Easy PDF & Word ===
Contributors: biztech
Tags: pdf, invoice, arabic, woocommerce, zatca
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PDF and Word documents in any language, with first-class Arabic: WooCommerce invoices, tax invoices, credit notes and delivery notes.

== Description ==

Easy PDF & Word makes PDF and Word (.docx) documents in Arabic, English and Turkish. Arabic is right to left, with joined letters, Arabic numerals, amounts in words and Hijri dates.

= WooCommerce =

* An invoice for each order: a tax invoice, with the ZATCA QR code for Saudi Arabia, or the Egyptian e-invoice layout. Invoices get sequential numbers (INV-0001), kept with the order.
* A credit note for each refund, and a delivery note without prices.
* PDF and Word buttons on the order page, an invoice button in the orders list, and "Download invoices (ZIP)" for the selected orders.
* Customers download the invoice and the credit notes of their own paid orders from My account.
* The invoice goes with the "Completed order" and "Order details" emails, and the credit note with "Refunded order".
* Prices are before tax. Coupons show as discounts on the lines, and shipping and fees as lines of their own, so the document's total matches the order's.

= For developers =

`easy_pdf_word()` gives the document factory of the [Easy PDF & Word library](https://easypdf.biztech-eg.com), with its 12 ready-made templates (invoice, quotation, receipt, contract, certificate and more) or your own:

`
easy_pdf_word()
    ->template( 'invoice', $data )
    ->pdf( 'invoice.pdf' )
    ->send();
`

= Templates in your theme =

Copy a template folder into an `easy-pdf-word` folder in your theme to change it. Your copy is used instead of the bundled one.

= Hooks =

* `easy_pdf_word_config` filters the library's settings.
* `easy_pdf_word_template_paths` filters the folders searched for templates.
* `easy_pdf_word_factory` fires once the factory is made.
* `easy_pdf_word_warning` fires when the library warns, for example when an image is left out.
* `easy_pdf_word_settings` filters the plugin's settings.
* `easy_pdf_word_woocommerce_data` filters the data of an order's document, and `easy_pdf_word_woocommerce_template` its template.
* `easy_pdf_word_woocommerce_filename` filters a document's file name, and `easy_pdf_word_document_number` a new invoice or credit note number.
* `easy_pdf_word_customer_can_download` filters whether a customer can download a document.

== Installation ==

1. Upload the plugin zip from Plugins > Add New > Upload Plugin, or install it from the plugin directory.
2. Activate it.

The server needs PHP 8.2 or newer with the `mbstring` and `gd` extensions, `zip` for Word files and `intl` for Hijri dates.

== Frequently Asked Questions ==

= Another plugin uses mPDF too. Do they clash? =

No. The bundled libraries are renamed under the plugin's own prefix, so another copy of mPDF or PhpWord does not affect them.

= My orders have several VAT rates. =

The tax invoice shows one VAT rate: an order with several rates shows their average, and the totals still match the order. The Egyptian layout shows each line's own rate.

= Where are temporary files written? =

To WordPress's temp folder, in an `easy-pdf-word` folder. On shared hosting, set `WP_TEMP_DIR` in `wp-config.php` to a folder of your own account.

== Changelog ==

= 1.0.0 =
* First release.
