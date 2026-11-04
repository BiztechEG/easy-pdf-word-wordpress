<?php

namespace BiztechEG\EasyPdfWordWP\Tests\Plugin;

use BiztechEG\EasyPdfWordWP\Documents;
use BiztechEG\EasyPdfWordWP\Vendor\BiztechEG\EasyPdfWord\DocumentFactory;
use WP_UnitTestCase;

/**
 * The document factory that easy_pdf_word() returns.
 */
class DocumentsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Documents::reset();
	}

	public function tear_down(): void {
		Documents::reset();
		parent::tear_down();
	}

	private static function invoice(): array {
		return array(
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
		);
	}

	public function test_the_factory_is_the_prefixed_library_made_once(): void {
		$factory = easy_pdf_word();

		$this->assertInstanceOf( DocumentFactory::class, $factory );
		$this->assertSame( $factory, easy_pdf_word() );

		Documents::reset();
		$this->assertNotSame( $factory, easy_pdf_word() );
	}

	public function test_documents_follow_the_site_language(): void {
		$this->assertSame( 'en', Documents::locale() );

		add_filter( 'locale', fn () => 'ar' );
		$this->assertSame( 'ar', Documents::locale() );

		add_filter( 'locale', fn () => 'tr_TR', 11 );
		$this->assertSame( 'tr', Documents::locale() );

		add_filter( 'locale', fn () => 'fr_FR', 12 );
		$this->assertSame( 'en', Documents::locale(), 'A language without templates falls back to English.' );

		$this->assertSame( 'en', Documents::config()['locale'] );
	}

	public function test_the_site_name_is_the_company_as_plain_text(): void {
		update_option( 'blogname', 'Noor & Sons' );

		$this->assertSame( 'Noor & Sons', Documents::config()['theme']['company']['name'] );
	}

	public function test_temp_files_go_to_the_wordpress_temp_folder(): void {
		$config = Documents::config();
		$folder = untrailingslashit( get_temp_dir() ) . '/easy-pdf-word';

		$this->assertSame( $folder, $config['temp_dir'] );
		$this->assertSame( $folder . '/mpdf', $config['pdf']['drivers']['mpdf']['temp_dir'] );
	}

	public function test_images_only_come_from_the_media_folder(): void {
		$images = Documents::config()['images'];

		$this->assertSame( array( wp_get_upload_dir()['basedir'] ), $images['paths'] );
		$this->assertFalse( $images['remote'] );
	}

	public function test_settings_can_be_filtered(): void {
		add_filter(
			'easy_pdf_word_config',
			function ( array $config ) {
				$config['numerals'] = 'arabic';

				return $config;
			}
		);

		$this->assertSame( 'arabic', Documents::config()['numerals'] );
		$this->assertSame( 'arabic', easy_pdf_word()->services()->config( 'numerals' ) );
	}

	public function test_the_factory_hook_gets_the_factory(): void {
		$seen = null;
		add_action(
			'easy_pdf_word_factory',
			function ( $factory ) use ( &$seen ) {
				$seen = $factory;
			}
		);

		$this->assertSame( easy_pdf_word(), $seen );
	}

	public function test_a_template_in_the_theme_wins(): void {
		$theme = get_stylesheet_directory() . '/easy-pdf-word';
		$this->assertSame( $theme, Documents::template_paths()[0] );

		$folder = get_temp_dir() . 'easy-pdf-word-tests-' . wp_generate_password( 6, false );
		mkdir( $folder . '/greeting', 0777, true );
		file_put_contents( $folder . '/greeting/template.php', "<?php\n\nreturn ['title' => 'Greeting'];\n" );
		file_put_contents( $folder . '/greeting/pdf.html.php', '<p>Hello from the theme</p>' );

		add_filter( 'easy_pdf_word_template_paths', fn ( array $paths ) => array_merge( array( $folder ), $paths ) );

		$html = easy_pdf_word()->template( 'greeting' )->toHtml();
		$this->assertStringContainsString( 'Hello from the theme', $html );
	}

	public function test_an_arabic_pdf_invoice(): void {
		add_filter( 'locale', fn () => 'ar' );

		$file = easy_pdf_word()->template( 'invoice', self::invoice() )->pdf( 'invoice.pdf' );

		$this->assertStringStartsWith( '%PDF', $file->content() );
		$this->assertSame( 'invoice.pdf', $file->filename() );
	}

	public function test_a_word_file(): void {
		$content = easy_pdf_word()->make()->locale( 'ar' )->paragraph( 'مرحبا' )->word()->content();

		$this->assertStringStartsWith( 'PK', $content );
	}

	public function test_warnings_reach_the_warning_hook(): void {
		$warnings = array();
		add_action(
			'easy_pdf_word_warning',
			function ( string $message ) use ( &$warnings ) {
				$warnings[] = $message;
			}
		);

		// Outside the media folder, so the logo is left out with a warning.
		easy_pdf_word()
			->template( 'invoice', self::invoice() )
			->theme( array( 'logo' => ABSPATH . 'wp-admin/images/wordpress-logo.png' ) )
			->toHtml();

		$this->assertNotEmpty( $warnings );
	}
}
