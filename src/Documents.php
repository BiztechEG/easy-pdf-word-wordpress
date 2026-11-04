<?php

namespace BiztechEG\EasyPdfWordWP;

use BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\EasyPdfWord;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the library's document factory from WordPress: the site language,
 * the site name, WordPress's temp folder and the media folder.
 */
final class Documents {

	/** The languages the bundled templates are written in. */
	public const LOCALES = array( 'ar', 'en', 'tr' );

	private static ?DocumentFactory $factory = null;

	/** One factory per request, made on first use. */
	public static function factory(): DocumentFactory {
		if ( null === self::$factory ) {
			self::$factory = EasyPdfWord::create(
				self::config(),
				array( 'warn' => self::warn( ... ) )
			);

			/**
			 * Fires once the factory is made, for example to add a PDF engine with
			 * $factory->extend().
			 *
			 * @param DocumentFactory $factory The document factory.
			 */
			do_action( 'easy_pdf_word_factory', self::$factory );
		}

		return self::$factory;
	}

	/** Forget the factory, so the next call builds it from the current settings. */
	public static function reset(): void {
		self::$factory = null;
	}

	/** The library's settings; see https://easypdf.biztech-eg.com/guide/configuration. */
	public static function config(): array {
		$temp_dir = untrailingslashit( get_temp_dir() ) . '/easy-pdf-word';

		$settings = Settings::all();
		$config   = array(
			'locale'    => self::locale(),
			'numerals'  => 'arabic' === $settings['numerals'] ? 'arabic' : 'latin',
			// WordPress's temp folder: WP_TEMP_DIR when the site sets it.
			'temp_dir'  => $temp_dir,
			'pdf'       => array(
				'driver'   => self::engine( $settings ),
				'fallback' => 'mpdf',
				'drivers'  => array(
					'mpdf'      => array( 'temp_dir' => $temp_dir . '/mpdf' ),
					'gotenberg' => array( 'url' => (string) $settings['gotenberg_url'] ),
				),
			),
			'fonts'     => array(
				'default'     => (string) $settings['font'],
				'default_ltr' => (string) $settings['font'],
			),
			'images'    => array(
				// The logo and stamps come from the media library.
				'paths'  => array( wp_get_upload_dir()['basedir'] ),
				'remote' => false,
			),
			'templates' => array( 'paths' => self::template_paths() ),
			'theme'     => array(
				'primary' => self::color( (string) $settings['primary_color'] ),
				'logo'    => Settings::logo_path(),
				'company' => Settings::company(),
			),
		);

		/**
		 * Filters the library's settings before the factory is made.
		 *
		 * @param array $config The settings, merged over the library's defaults.
		 */
		return (array) apply_filters( 'easy_pdf_word_config', $config );
	}

	/** A hex colour, or the default teal for anything else (an empty or bad value). */
	private static function color( string $color ): string {
		$color = sanitize_hex_color( $color );

		return is_string( $color ) && '' !== $color ? $color : '#0F766E';
	}

	/** The PDF engine: mPDF, or Gotenberg when it is chosen and has an address. */
	private static function engine( array $settings ): string {
		return 'gotenberg' === $settings['engine'] && '' !== (string) $settings['gotenberg_url'] ? 'gotenberg' : 'mpdf';
	}

	/**
	 * The language setting, or the site language when the templates have it,
	 * else English. A shop manager whose own profile language differs still
	 * gets documents in the site language; WooCommerce emails switch to the
	 * customer's language.
	 */
	public static function locale(): string {
		$chosen = (string) Settings::get( 'language' );

		if ( in_array( $chosen, self::LOCALES, true ) ) {
			return $chosen;
		}

		$language = strtolower( substr( get_locale(), 0, 2 ) );

		return in_array( $language, self::LOCALES, true ) ? $language : 'en';
	}

	/**
	 * Folders searched for templates before the bundled ones: an
	 * "easy-pdf-word" folder in the child theme, then in the parent theme.
	 *
	 * @return list<string>
	 */
	public static function template_paths(): array {
		$paths = array_values(
			array_unique(
				array(
					get_stylesheet_directory() . '/easy-pdf-word',
					get_template_directory() . '/easy-pdf-word',
				)
			)
		);

		/**
		 * Filters the folders searched for templates before the bundled ones.
		 *
		 * @param list<string> $paths Folders, first match wins.
		 */
		return array_values( (array) apply_filters( 'easy_pdf_word_template_paths', $paths ) );
	}

	/** A failed engine or a left-out image: for developers, not visitors. */
	private static function warn( string $message ): void {
		/**
		 * Fires when the library warns, for example when an image is left out.
		 *
		 * @param string $message The warning.
		 */
		do_action( 'easy_pdf_word_warning', $message );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only with WP_DEBUG.
			error_log( 'Easy PDF & Word: ' . $message );
		}
	}
}
