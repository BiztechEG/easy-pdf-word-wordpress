<?php
/**
 * Loads the bundled libraries and the plugin's classes, then starts the plugin.
 */

defined( 'ABSPATH' ) || exit;

$easy_pdf_word_libraries = dirname( __DIR__ ) . '/vendor-prefixed/autoload.php';

if ( ! is_file( $easy_pdf_word_libraries ) ) {
	// A copy from git without the build step: see CONTRIBUTING.md.
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Easy PDF & Word is missing its libraries. Install the plugin from its zip file.', 'easy-pdf-word' );
			echo '</p></div>';
		}
	);

	return;
}

require $easy_pdf_word_libraries;
unset( $easy_pdf_word_libraries );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'BiztechEG\\EasyPdfWordWP\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

/*
 * Templates written for the library, in a theme or copied from its docs, name
 * its classes without the prefix (use BiztechEG\EasyPdfWord\Support\Currency).
 * Those names load the bundled classes.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'BiztechEG\\EasyPdfWord\\' ) ) {
			return;
		}

		$bundled = 'BiztechEG\\EasyPdfWordWP\\Vendor\\' . $class_name;

		if ( class_exists( $bundled ) || interface_exists( $bundled ) || trait_exists( $bundled ) ) {
			class_alias( $bundled, $class_name );
		}
	}
);

require __DIR__ . '/functions.php';

BiztechEG\EasyPdfWordWP\Plugin::boot();
