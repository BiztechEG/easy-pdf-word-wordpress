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

		$config = array(
			'locale'    => self::locale(),
			// WordPress's temp folder: WP_TEMP_DIR when the site sets it.
			'temp_dir'  => $temp_dir,
			'pdf'       => array(
				'drivers' => array(
					'mpdf' => array( 'temp_dir' => $temp_dir . '/mpdf' ),
				),
			),
			'images'    => array(
				// The logo and stamps come from the media library.
				'paths'  => array( wp_get_upload_dir()['basedir'] ),
				'remote' => false,
			),
			'templates' => array( 'paths' => self::template_paths() ),
			'theme'     => array(
				'company' => array(
					// get_bloginfo() escapes for HTML; the templates escape again.
					'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				),
			),
		);

		/**
		 * Filters the library's settings before the factory is made.
		 *
		 * @param array $config The settings, merged over the library's defaults.
		 */
		return (array) apply_filters( 'easy_pdf_word_config', $config );
	}

	/** The site language when the templates have it, else English. */
	public static function locale(): string {
		$language = strtolower( substr( determine_locale(), 0, 2 ) );

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
