<?php

namespace BiztechEG\EasyPdfWordWP\Tests\Plugin;

use BiztechEG\EasyPdfWordWP\Documents;
use WP_UnitTestCase;

/**
 * The libraries are bundled under the plugin's prefix, so other plugins'
 * copies of mPDF or PhpWord never meet them.
 */
class BundledLibrariesTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Documents::reset();
	}

	public function tear_down(): void {
		Documents::reset();
		parent::tear_down();
	}

	public function test_the_libraries_load_under_the_prefix(): void {
		easy_pdf_word()->make()->paragraph( 'x' )->pdf()->content();
		easy_pdf_word()->make()->paragraph( 'x' )->word()->content();

		$this->assertTrue( class_exists( 'BiztechEG\\EasyPdfWordWP\\Vendor\\Mpdf\\Mpdf', false ) );
		$this->assertTrue( class_exists( 'BiztechEG\\EasyPdfWordWP\\Vendor\\PhpOffice\\PhpWord\\PhpWord', false ) );
		$this->assertFalse( class_exists( 'Mpdf\\Mpdf' ) );
		$this->assertFalse( class_exists( 'PhpOffice\\PhpWord\\PhpWord' ) );
	}

	public function test_the_library_class_names_reach_the_bundled_classes(): void {
		$currency = 'BiztechEG\\EasyPdfWord\\Support\\Currency';

		$this->assertTrue( class_exists( $currency ) );
		$this->assertSame( 'BiztechEG\\EasyPdfWordWP\\Vendor\\' . $currency, ( new \ReflectionClass( $currency ) )->getName() );
	}

	public function test_a_theme_template_written_for_the_library_works(): void {
		$folder = get_temp_dir() . 'easy-pdf-word-tests-' . wp_generate_password( 6, false );
		mkdir( $folder . '/price', 0777, true );
		file_put_contents( $folder . '/price/template.php', "<?php\n\nreturn ['title' => 'Price'];\n" );
		file_put_contents(
			$folder . '/price/pdf.html.php',
			"<?php use BiztechEG\\EasyPdfWord\\Support\\Currency; ?>\n<p>decimals: <?= Currency::decimals('KWD') ?></p>"
		);

		add_filter( 'easy_pdf_word_template_paths', fn () => array( $folder ) );

		$html = easy_pdf_word()->template( 'price' )->toHtml();

		$this->assertStringContainsString( 'decimals: 3', $html );
	}
}
