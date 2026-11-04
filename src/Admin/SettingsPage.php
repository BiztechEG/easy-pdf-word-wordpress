<?php

namespace BiztechEG\EasyPdfWordWP\Admin;

use BiztechEG\EasyPdfWordWP\Documents;
use BiztechEG\EasyPdfWordWP\Settings;
use BiztechEG\EasyPdfWordWP\WooCommerce\Numbers;
use BiztechEG\EasyPdfWordWP\WooCommerce\OrderDocument;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * The settings page: under WooCommerce when it is active (shop managers can
 * use it), else under Settings. It saves the easy_pdf_word_settings option
 * through WordPress's Settings API, which checks the nonce and the
 * capability.
 */
final class SettingsPage {

	public const PAGE = 'easy-pdf-word';

	public const GROUP = 'easy_pdf_word';

	public const PREVIEW = 'easy_pdf_word_preview';

	public const LANGUAGES = array( 'ar', 'en', 'tr' );

	public const FONTS = array( 'cairo', 'tajawal', 'naskh' );

	public const INVOICE_TYPES = array( 'standard', 'zatca', 'egypt' );

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'scripts' ) );
		add_action( 'admin_post_' . self::PREVIEW, array( self::class, 'preview' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( self::class, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EASY_PDF_WORD_FILE ), array( self::class, 'action_links' ) );
		// The next document is made with the saved settings.
		add_action( 'update_option_' . Settings::OPTION, array( Documents::class, 'reset' ) );
		add_action( 'add_option_' . Settings::OPTION, array( Documents::class, 'reset' ) );
	}

	/** Shop managers with WooCommerce, administrators without it. */
	public static function capability(): string {
		return self::woocommerce() ? 'manage_woocommerce' : 'manage_options';
	}

	public static function url(): string {
		return admin_url( ( self::woocommerce() ? 'admin.php' : 'options-general.php' ) . '?page=' . self::PAGE );
	}

	public static function add_page(): void {
		$title = __( 'Easy PDF & Word', 'easy-pdf-word' );

		if ( self::woocommerce() ) {
			add_submenu_page( 'woocommerce', $title, $title, self::capability(), self::PAGE, array( self::class, 'render' ) );
		} else {
			add_options_page( $title, $title, self::capability(), self::PAGE, array( self::class, 'render' ) );
		}
	}

	public static function register_setting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	public static function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'easy-pdf-word' ) . '</a>' );

		return $links;
	}

	public static function scripts( $hook ): void {
		if ( ! is_string( $hook ) || ! str_ends_with( $hook, '_page_' . self::PAGE ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'easy-pdf-word-settings', plugins_url( 'assets/settings.js', EASY_PDF_WORD_FILE ), array( 'jquery' ), EASY_PDF_WORD_VERSION, true );
	}

	/**
	 * Keeps only known values. The form marks itself with _form, so an
	 * unticked box there means "none"; a call to update_option() without it
	 * changes only the settings it names.
	 *
	 * @param mixed $input The submitted settings.
	 */
	public static function sanitize( $input ): array {
		// options.php has already unslashed the form.
		$input    = is_array( $input ) ? $input : array();
		$form     = ! empty( $input['_form'] );
		$previous = get_option( Settings::OPTION, array() );
		$settings = array_merge( Settings::defaults(), is_array( $previous ) ? $previous : array() );

		$text = array( 'company_name', 'company_phone', 'tax_number', 'invoice_prefix', 'credit_note_prefix' );

		foreach ( $text as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$settings[ $key ] = mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, 'company_name' === $key ? 200 : 50 );
			}
		}

		if ( isset( $input['company_address'] ) ) {
			$settings['company_address'] = mb_substr( sanitize_textarea_field( (string) $input['company_address'] ), 0, 500 );
		}

		if ( isset( $input['company_email'] ) ) {
			$settings['company_email'] = sanitize_email( (string) $input['company_email'] );
		}

		if ( isset( $input['logo'] ) ) {
			$logo             = absint( $input['logo'] );
			$settings['logo'] = $logo && wp_attachment_is_image( $logo ) ? $logo : 0;
		}

		if ( isset( $input['primary_color'] ) ) {
			$color                     = sanitize_hex_color( (string) $input['primary_color'] );
			$settings['primary_color'] = is_string( $color ) && '' !== $color ? $color : Settings::defaults()['primary_color'];
		}

		$choices = array(
			'language'     => array_merge( array( '' ), self::LANGUAGES ),
			'numerals'     => array( 'latin', 'arabic' ),
			'font'         => self::FONTS,
			'engine'       => array( 'mpdf', 'gotenberg' ),
			'invoice_type' => self::INVOICE_TYPES,
		);

		foreach ( $choices as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && in_array( (string) $input[ $key ], $allowed, true ) ) {
				$settings[ $key ] = (string) $input[ $key ];
			}
		}

		if ( isset( $input['gotenberg_url'] ) ) {
			$settings['gotenberg_url'] = esc_url_raw( trim( (string) $input['gotenberg_url'] ), array( 'http', 'https' ) );
		}

		if ( 'gotenberg' === $settings['engine'] && '' === $settings['gotenberg_url'] ) {
			$settings['engine'] = 'mpdf';
			self::error( 'gotenberg', __( 'Gotenberg needs its address, so the PDF engine stays mPDF.', 'easy-pdf-word' ) );
		}

		if ( isset( $input['number_padding'] ) ) {
			$settings['number_padding'] = max( 1, min( 12, absint( $input['number_padding'] ) ) );
		}

		// The WooCommerce fields are on the form only with WooCommerce.
		if ( ! empty( $input['_woocommerce'] ) || ( ! $form && self::woocommerce() ) ) {
			$settings = self::sanitize_woocommerce( $input, $settings, $form );
		}

		unset( $settings['_form'], $settings['_woocommerce'], $settings['next_invoice'], $settings['next_credit_note'] );

		return $settings;
	}

	private static function sanitize_woocommerce( array $input, array $settings, bool $form ): array {
		$lists = array(
			'customer_documents' => OrderDocument::TYPES,
			'invoice_statuses'   => array_map( fn ( $status ) => substr( $status, 3 ), array_keys( wc_get_order_statuses() ) ),
		);

		foreach ( $lists as $key => $allowed ) {
			if ( $form || isset( $input[ $key ] ) ) {
				$settings[ $key ] = array_values( array_intersect( (array) ( $input[ $key ] ?? array() ), $allowed ) );
			}
		}

		if ( $form || isset( $input['attach'] ) ) {
			$emails = array_keys( self::emails() );
			$attach = (array) ( $input['attach'] ?? array() );

			$settings['attach'] = array();

			foreach ( OrderDocument::TYPES as $type ) {
				$settings['attach'][ $type ] = array_values( array_intersect( (array) ( $attach[ $type ] ?? array() ), $emails ) );
			}
		}

		// The next numbers are kept by the sequences, not in the settings.
		foreach ( array(
			'invoice'     => 'next_invoice',
			'credit-note' => 'next_credit_note',
		) as $sequence => $key ) {
			if ( ! isset( $input[ $key ] ) || '' === (string) $input[ $key ] ) {
				continue;
			}

			$next = absint( $input[ $key ] );

			if ( Numbers::peek( $sequence ) === $next ) {
				continue;
			}

			if ( $next < Numbers::peek( $sequence ) ) {
				/* translators: %d: the next number as it is now. */
				self::error( $key, sprintf( __( 'A next number can only go up, so two documents never share a number. It stays %d.', 'easy-pdf-word' ), Numbers::peek( $sequence ) ) );
				continue;
			}

			Numbers::restart( $sequence, $next );
		}

		return $settings;
	}

	public static function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'easy-pdf-word' ), '', array( 'response' => 403 ) );
		}

		$settings = Settings::all();

		echo '<div class="wrap"><h1>' . esc_html__( 'Easy PDF & Word', 'easy-pdf-word' ) . '</h1>';

		// Pages under WooCommerce do not print the Settings API's messages themselves.
		if ( self::woocommerce() ) {
			settings_errors();
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( self::GROUP );
		self::hidden( '_form', '1' );

		self::section( __( 'Company', 'easy-pdf-word' ), __( 'Shown on every document. Leave a field empty to use the site name and the WooCommerce store address.', 'easy-pdf-word' ) );
		self::text( 'company_name', __( 'Name', 'easy-pdf-word' ), $settings, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		self::textarea( 'company_address', __( 'Address', 'easy-pdf-word' ), $settings, Settings::store_address() );
		self::text( 'company_phone', __( 'Phone', 'easy-pdf-word' ), $settings );
		self::text( 'company_email', __( 'Email', 'easy-pdf-word' ), $settings, '', 'email' );
		self::text( 'tax_number', __( 'Tax number', 'easy-pdf-word' ), $settings, '', 'text', __( 'The VAT number (Saudi Arabia), or the tax registration number that the Egyptian invoice needs.', 'easy-pdf-word' ) );
		self::logo( $settings );
		self::text( 'primary_color', __( 'Main colour', 'easy-pdf-word' ), $settings, '', 'color' );
		self::end_section();

		self::section( __( 'Documents', 'easy-pdf-word' ) );
		self::select(
			'language',
			__( 'Language', 'easy-pdf-word' ),
			$settings,
			array(
				''   => __( 'The site language (the customer\'s in emails)', 'easy-pdf-word' ),
				'ar' => 'العربية',
				'en' => 'English',
				'tr' => 'Türkçe',
			)
		);
		self::select(
			'numerals',
			__( 'Digits', 'easy-pdf-word' ),
			$settings,
			array(
				'latin'  => '0123456789',
				'arabic' => '٠١٢٣٤٥٦٧٨٩',
			)
		);
		self::select(
			'font',
			__( 'Font', 'easy-pdf-word' ),
			$settings,
			array(
				'cairo'   => 'Cairo',
				'tajawal' => 'Tajawal',
				'naskh'   => 'Noto Naskh Arabic',
			)
		);
		self::select(
			'engine',
			__( 'PDF engine', 'easy-pdf-word' ),
			$settings,
			array(
				'mpdf'      => __( 'mPDF (built in)', 'easy-pdf-word' ),
				'gotenberg' => __( 'Gotenberg (Chromium as a service)', 'easy-pdf-word' ),
			),
			__( 'mPDF works on any host. Gotenberg draws pages as Chrome does; when it cannot be reached, mPDF is used.', 'easy-pdf-word' )
		);
		self::text( 'gotenberg_url', __( 'Gotenberg address', 'easy-pdf-word' ), $settings, 'http://localhost:3000', 'url', '', 'easy-pdf-word-gotenberg' );
		self::end_section();

		if ( self::woocommerce() ) {
			self::render_woocommerce( $settings );
		}

		submit_button();
		echo '</form>';

		self::render_preview();
		echo '</div>';
	}

	private static function render_woocommerce( array $settings ): void {
		self::hidden( '_woocommerce', '1' );

		self::section( __( 'Invoices', 'easy-pdf-word' ) );
		self::select(
			'invoice_type',
			__( 'Invoice', 'easy-pdf-word' ),
			$settings,
			array(
				'standard' => __( 'Tax invoice', 'easy-pdf-word' ),
				'zatca'    => __( 'Tax invoice with the ZATCA QR code (Saudi Arabia)', 'easy-pdf-word' ),
				'egypt'    => __( 'Egyptian e-invoice layout', 'easy-pdf-word' ),
			)
		);
		self::text( 'invoice_prefix', __( 'Invoice number prefix', 'easy-pdf-word' ), $settings, '', 'text', __( '{year} becomes the current year, for example INV-{year}-.', 'easy-pdf-word' ) );
		self::text( 'credit_note_prefix', __( 'Credit note number prefix', 'easy-pdf-word' ), $settings );
		self::text( 'number_padding', __( 'Digits in a number', 'easy-pdf-word' ), $settings, '', 'number', __( '4 gives INV-0001.', 'easy-pdf-word' ) );
		self::number( 'next_invoice', __( 'Next invoice number', 'easy-pdf-word' ), Numbers::peek( 'invoice' ) );
		self::number( 'next_credit_note', __( 'Next credit note number', 'easy-pdf-word' ), Numbers::peek( 'credit-note' ) );
		self::end_section();

		$statuses = array();
		foreach ( wc_get_order_statuses() as $status => $label ) {
			$statuses[ substr( $status, 3 ) ] = $label;
		}

		$documents = array_combine( OrderDocument::TYPES, array_map( array( OrderDocument::class, 'label' ), OrderDocument::TYPES ) );

		self::section( __( 'Customers', 'easy-pdf-word' ) );
		self::checkboxes( 'customer_documents', __( 'In My account', 'easy-pdf-word' ), $documents, (array) $settings['customer_documents'], __( 'The documents customers can download for their own orders.', 'easy-pdf-word' ) );
		self::checkboxes( 'invoice_statuses', __( 'Invoice available when the order is', 'easy-pdf-word' ), $statuses, (array) $settings['invoice_statuses'], __( 'Before then, customers cannot download the invoice and emails do not carry it.', 'easy-pdf-word' ) );
		self::end_section();

		self::section( __( 'Emails', 'easy-pdf-word' ), __( 'The WooCommerce emails each document is attached to, as a PDF.', 'easy-pdf-word' ) );
		foreach ( OrderDocument::TYPES as $type ) {
			self::checkboxes( 'attach][' . $type, OrderDocument::label( $type ), self::emails(), (array) ( $settings['attach'][ $type ] ?? array() ) );
		}
		self::end_section();
	}

	private static function render_preview(): void {
		echo '<hr><h2>' . esc_html__( 'Sample invoice', 'easy-pdf-word' ) . '</h2>';
		echo '<p>' . esc_html__( 'Opens a sample invoice with the saved settings. Save your changes first.', 'easy-pdf-word' ) . '</p>';
		echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( self::preview_url() ) . '">' . esc_html__( 'Open the sample invoice (PDF)', 'easy-pdf-word' ) . '</a></p>';
	}

	public static function preview_url(): string {
		return add_query_arg(
			array(
				'action'   => self::PREVIEW,
				'_wpnonce' => wp_create_nonce( self::PREVIEW ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Shows the sample invoice in the browser: admin-post.php?action=easy_pdf_word_preview. */
	public static function preview(): void {
		self::sample_file()->send( inline: true );
		exit;
	}

	/** The sample invoice with the saved settings, after checking the nonce and the capability. */
	public static function sample_file(): \BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\Output\File {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'easy-pdf-word' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::PREVIEW );

		$egypt    = 'egypt' === Settings::get( 'invoice_type' );
		$template = $egypt ? 'eg-invoice' : 'invoice';
		$company  = Settings::company();
		$data     = easy_pdf_word()->templates()->get( $template )->sample();

		if ( $egypt ) {
			$data['issuer'] = array(
				'name'    => $company['name'],
				'rin'     => '' !== $company['tax_number'] ? $company['tax_number'] : '000-000-000',
				'address' => $company['address'],
			);
		} else {
			// The seller is the company from the settings.
			unset( $data['seller'] );
			$data['qr'] = 'zatca' === Settings::get( 'invoice_type' ) ? 'zatca' : null;

			if ( function_exists( 'get_woocommerce_currency' ) ) {
				$data['invoice']['currency'] = get_woocommerce_currency();
			}
		}

		try {
			return easy_pdf_word()->template( $template, $data )->pdf( 'sample-invoice.pdf' );
		} catch ( Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 500 ) );
		}
	}

	/** @return array<string, string> WooCommerce's order emails by ID, with who gets each. */
	public static function emails(): array {
		$emails = array();

		foreach ( WC()->mailer()->get_emails() as $email ) {
			// Order emails only: the documents belong to an order.
			if ( ! str_contains( $email->id, 'order' ) && ! in_array( $email->id, array( 'customer_invoice', 'customer_note' ), true ) ) {
				continue;
			}

			$emails[ $email->id ] = sprintf(
				$email->is_customer_email()
					/* translators: %s: a WooCommerce email, such as Completed order. */
					? __( '%s (to the customer)', 'easy-pdf-word' )
					/* translators: %s: a WooCommerce email, such as New order. */
					: __( '%s (to the shop)', 'easy-pdf-word' ),
				wp_strip_all_tags( $email->get_title() )
			);
		}

		return $emails;
	}

	private static function woocommerce(): bool {
		return function_exists( 'WC' );
	}

	/** A message on the settings page, once: WordPress sanitizes a new option twice. */
	private static function error( string $code, string $message ): void {
		if ( ! function_exists( 'add_settings_error' ) ) {
			return;
		}

		foreach ( get_settings_errors( Settings::OPTION ) as $error ) {
			if ( $error['code'] === $code ) {
				return;
			}
		}

		add_settings_error( Settings::OPTION, $code, $message );
	}

	private static function name( string $key ): string {
		return Settings::OPTION . '[' . $key . ']';
	}

	private static function id( string $key ): string {
		return 'easy-pdf-word-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $key ) ), '-' );
	}

	private static function hidden( string $key, string $value ): void {
		printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( self::name( $key ) ), esc_attr( $value ) );
	}

	private static function section( string $title, string $description = '' ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';

		if ( '' !== $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
	}

	private static function end_section(): void {
		echo '</tbody></table>';
	}

	private static function row( string $key, string $label, string $field, string $description = '', string $row_class = '' ): void {
		printf( '<tr class="%s"><th scope="row"><label for="%s">%s</label></th><td>', esc_attr( $row_class ), esc_attr( self::id( $key ) ), esc_html( $label ) );
		echo wp_kses( $field, self::allowed_html() );

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}

		echo '</td></tr>';
	}

	/** The form fields the settings page prints, for wp_kses(). */
	private static function allowed_html(): array {
		$common = array(
			'id'    => true,
			'name'  => true,
			'class' => true,
		);

		return array(
			'input'    => $common + array(
				'type'        => true,
				'value'       => true,
				'placeholder' => true,
				'min'         => true,
				'checked'     => true,
			),
			'select'   => $common,
			'option'   => array(
				'value'    => true,
				'selected' => true,
			),
			'textarea' => $common + array(
				'rows'        => true,
				'placeholder' => true,
			),
			'fieldset' => array(),
			'legend'   => array( 'class' => true ),
			'label'    => array(),
			'br'       => array(),
			'div'      => array( 'class' => true ),
			'button'   => array(
				'type'        => true,
				'class'       => true,
				'hidden'      => true,
				'data-title'  => true,
				'data-button' => true,
			),
			'img'      => array(
				'src'      => true,
				'srcset'   => true,
				'sizes'    => true,
				'alt'      => true,
				'width'    => true,
				'height'   => true,
				'class'    => true,
				'loading'  => true,
				'decoding' => true,
			),
		);
	}

	private static function text( string $key, string $label, array $settings, string $placeholder = '', string $type = 'text', string $description = '', string $row_class = '' ): void {
		$field = sprintf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" class="%6$s">',
			esc_attr( $type ),
			esc_attr( self::id( $key ) ),
			esc_attr( self::name( $key ) ),
			esc_attr( (string) ( $settings[ $key ] ?? '' ) ),
			esc_attr( $placeholder ),
			in_array( $type, array( 'number', 'color' ), true ) ? 'small-text' : 'regular-text'
		);

		self::row( $key, $label, $field, $description, $row_class );
	}

	private static function number( string $key, string $label, int $value ): void {
		$field = sprintf(
			'<input type="number" min="1" id="%1$s" name="%2$s" value="%3$d" class="small-text">',
			esc_attr( self::id( $key ) ),
			esc_attr( self::name( $key ) ),
			$value
		);

		self::row( $key, $label, $field, __( 'Can only go up.', 'easy-pdf-word' ) );
	}

	private static function textarea( string $key, string $label, array $settings, string $placeholder = '' ): void {
		$field = sprintf(
			'<textarea id="%1$s" name="%2$s" rows="3" class="large-text" placeholder="%3$s">%4$s</textarea>',
			esc_attr( self::id( $key ) ),
			esc_attr( self::name( $key ) ),
			esc_attr( $placeholder ),
			esc_textarea( (string) ( $settings[ $key ] ?? '' ) )
		);

		self::row( $key, $label, $field );
	}

	private static function select( string $key, string $label, array $settings, array $options, string $description = '' ): void {
		$field = sprintf( '<select id="%1$s" name="%2$s">', esc_attr( self::id( $key ) ), esc_attr( self::name( $key ) ) );

		foreach ( $options as $value => $text ) {
			$field .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $value ), selected( (string) ( $settings[ $key ] ?? '' ), (string) $value, false ), esc_html( $text ) );
		}

		self::row( $key, $label, $field . '</select>', $description );
	}

	private static function checkboxes( string $key, string $label, array $options, array $checked, string $description = '' ): void {
		$field = '<fieldset><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';

		foreach ( $options as $value => $text ) {
			$field .= sprintf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s"%3$s> %4$s</label><br>',
				esc_attr( self::name( $key ) ),
				esc_attr( (string) $value ),
				checked( in_array( (string) $value, array_map( 'strval', $checked ), true ), true, false ),
				esc_html( $text )
			);
		}

		self::row( $key, $label, $field . '</fieldset>', $description );
	}

	private static function logo( array $settings ): void {
		$id    = (int) $settings['logo'];
		$image = $id ? wp_get_attachment_image( $id, 'thumbnail' ) : '';

		$field = sprintf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$d"><div class="easy-pdf-word-logo-preview">%4$s</div>'
			. '<button type="button" class="button easy-pdf-word-logo-select" data-title="%5$s" data-button="%6$s">%7$s</button> '
			. '<button type="button" class="button-link easy-pdf-word-logo-remove"%8$s>%9$s</button>',
			esc_attr( self::id( 'logo' ) ),
			esc_attr( self::name( 'logo' ) ),
			$id,
			wp_kses_post( $image ),
			esc_attr__( 'Logo', 'easy-pdf-word' ),
			esc_attr__( 'Use this image', 'easy-pdf-word' ),
			esc_html__( 'Choose an image', 'easy-pdf-word' ),
			$id ? '' : ' hidden',
			esc_html__( 'Remove', 'easy-pdf-word' )
		);

		self::row( 'logo', __( 'Logo', 'easy-pdf-word' ), $field, __( 'A PNG or JPEG from the media library.', 'easy-pdf-word' ) );
	}
}
