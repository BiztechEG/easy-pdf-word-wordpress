<?php
/**
 * Makes an Arabic PDF invoice and a Word file with the libraries from a built
 * plugin, outside WordPress, and checks that only prefixed classes were loaded.
 *
 * php bin/smoke-test.php path/to/easy-pdf-word
 */

$plugin = rtrim( $argv[1] ?? '', '/' );

require $plugin . '/vendor-prefixed/autoload.php';

$factory_class = 'BiztechEG\\EasyPdfWordWP\\Vendor\\BiztechEG\\EasyPdfWord\\EasyPdfWord';
$temp          = sys_get_temp_dir() . '/easy-pdf-word-smoke-' . bin2hex( random_bytes( 4 ) );

$docs = $factory_class::create(
	array(
		'locale'   => 'ar',
		'temp_dir' => $temp,
		'pdf'      => array( 'drivers' => array( 'mpdf' => array( 'temp_dir' => $temp . '/mpdf' ) ) ),
	)
);

$pdf = $docs->template(
	'invoice',
	array(
		'invoice' => array(
			'number'   => 'INV-1',
			'date'     => '2026-11-03',
			'currency' => 'SAR',
			'tax_rate' => 15,
		),
		'buyer'   => array( 'name' => 'مؤسسة النور' ),
		'items'   => array(
			array(
				'description' => 'استشارة',
				'quantity'    => 2,
				'unit_price'  => 100,
			),
		),
		'qr'      => 'zatca',
	)
)->pdf()->content();

$word = $docs->template(
	'letter',
	array(
		'date'      => '2026-11-03',
		'recipient' => array( 'name' => 'مؤسسة النور' ),
		'subject'   => 'تجربة',
		'body'      => 'مرحبا',
		'sender'    => array( 'name' => 'عمرو' ),
	)
)->word()->content();

$failures = array();

if ( ! str_starts_with( $pdf, '%PDF' ) ) {
	$failures[] = 'The invoice is not a PDF.';
}

if ( ! str_starts_with( $word, 'PK' ) ) {
	$failures[] = 'The letter is not a Word file.';
}

$unprefixed = array_filter(
	array_merge( get_declared_classes(), get_declared_interfaces(), get_declared_traits() ),
	static fn ( string $name ) => preg_match( '/^(Mpdf|PhpOffice|BiztechEG\\\\EasyPdfWord\\\\|chillerlan|Psr|setasign|DeepCopy)/', $name ) === 1
);

if ( $unprefixed ) {
	$failures[] = 'Loaded without the prefix: ' . implode( ', ', array_slice( $unprefixed, 0, 10 ) );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

printf( "PDF: %d KB, Word: %d KB, only prefixed classes loaded.\n", strlen( $pdf ) / 1024, strlen( $word ) / 1024 );
