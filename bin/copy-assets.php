<?php
/**
 * Runs after Strauss (composer prefix). Strauss copies only the PHP classes of
 * each package, so this copies the files they read from disk: the library's
 * templates, languages and fonts, and mPDF's data and default font. The PHP
 * files among the templates name the library's classes, so they get the
 * prefix too.
 *
 * Usage: php bin/copy-assets.php [project folder]
 */

$root     = rtrim( $argv[1] ?? dirname( __DIR__ ), '/' );
$vendor   = $root . '/vendor';
$prefixed = $root . '/vendor-prefixed';
$prefix   = 'BiztechEG\\EasyPdfWordWP\\Vendor\\';

/**
 * Copies a folder, keeping only the files $keep accepts.
 *
 * @param string   $from The folder to copy.
 * @param string   $to   Where to copy it.
 * @param callable $keep Gets the file name, returns whether to copy it.
 */
function easy_pdf_word_copy_folder( string $from, string $to, callable $keep ): void {
	if ( ! is_dir( $from ) ) {
		fwrite( STDERR, "Missing folder: {$from}\n" );
		exit( 1 );
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $files as $file ) {
		if ( ! $file->isFile() || ! $keep( $file->getFilename() ) ) {
			continue;
		}

		$target = $to . substr( $file->getPathname(), strlen( $from ) );

		if ( ! is_dir( dirname( $target ) ) ) {
			mkdir( dirname( $target ), 0755, true );
		}

		copy( $file->getPathname(), $target );
	}
}

$all = static fn (): bool => true;

// The library: templates, languages and fonts.
$library = $prefixed . '/biztecheg/easy-pdf-word/resources';
easy_pdf_word_copy_folder( $vendor . '/biztecheg/easy-pdf-word/resources', $library, $all );

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $library, FilesystemIterator::SKIP_DOTS ) );

foreach ( $files as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	$code    = file_get_contents( $file->getPathname() );
	$changed = preg_replace( '/(?<![\\\\\w])BiztechEG\\\\EasyPdfWord\\\\/', addslashes( $prefix ) . 'BiztechEG\\\\EasyPdfWord\\\\', $code );
	$changed = preg_replace( '/(?<![\\\\\w>$])\b(tafqeet|hijri_date|arabic_numerals)\(/', 'easypdfwordwp_vendor_$1(', $changed );

	if ( $changed !== $code ) {
		file_put_contents( $file->getPathname(), $changed );
	}
}

// mPDF: its data files, and DejaVu Sans Condensed as its default font. The
// documents use the library's own fonts, so mPDF's other 80 MB of fonts stay out.
easy_pdf_word_copy_folder( $vendor . '/mpdf/mpdf/data', $prefixed . '/mpdf/mpdf/data', $all );
easy_pdf_word_copy_folder(
	$vendor . '/mpdf/mpdf/ttfonts',
	$prefixed . '/mpdf/mpdf/ttfonts',
	static fn ( string $name ): bool => str_starts_with( $name, 'DejaVuSansCondensed' ) || 'DejaVuinfo.txt' === $name
);

echo "Copied the library's resources and mPDF's data into vendor-prefixed/.\n";
