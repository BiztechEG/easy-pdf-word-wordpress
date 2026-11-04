<?php

namespace BiztechEG\EasyPdfWordWP\Tests\Plugin;

use BiztechEG\EasyPdfWordWP\Admin\SettingsPage;
use BiztechEG\EasyPdfWordWP\Documents;
use BiztechEG\EasyPdfWordWP\Settings;
use WP_UnitTestCase;
use WPDieException;

/**
 * The settings, their page, and how they reach the documents.
 */
class SettingsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		Documents::reset();
	}

	public function tear_down(): void {
		Documents::reset();
		parent::tear_down();
	}

	private function logo(): int {
		$file  = get_temp_dir() . 'easy-pdf-word-logo-' . wp_generate_password( 6, false ) . '.png';
		$image = imagecreatetruecolor( 40, 20 );
		imagepng( $image, $file );

		return self::factory()->attachment->create_upload_object( $file );
	}

	public function test_the_company_falls_back_to_the_site(): void {
		update_option( 'blogname', 'Noor & Sons' );

		$company = Settings::company();
		$this->assertSame( 'Noor & Sons', $company['name'] );
		$this->assertSame( '', $company['tax_number'] );

		update_option(
			Settings::OPTION,
			array(
				'company_name'    => 'شركة النور',
				'company_address' => 'الرياض',
				'company_phone'   => '0110000000',
				'tax_number'      => '300000000000003',
			)
		);

		$this->assertSame(
			array(
				'name'       => 'شركة النور',
				'address'    => 'الرياض',
				'phone'      => '0110000000',
				'email'      => '',
				'tax_number' => '300000000000003',
			),
			Settings::company()
		);
	}

	public function test_the_settings_reach_the_library(): void {
		$logo = $this->logo();
		update_option(
			Settings::OPTION,
			array(
				'language'      => 'ar',
				'numerals'      => 'arabic',
				'font'          => 'naskh',
				'primary_color' => '#123456',
				'logo'          => $logo,
				'company_name'  => 'شركة النور',
			)
		);

		$config = Documents::config();

		$this->assertSame( 'ar', $config['locale'], 'The language setting wins over the site language.' );
		$this->assertSame( 'arabic', $config['numerals'] );
		$this->assertSame( 'naskh', $config['fonts']['default'] );
		$this->assertSame( '#123456', $config['theme']['primary'] );
		$this->assertSame( get_attached_file( $logo ), $config['theme']['logo'] );
		$this->assertSame( 'شركة النور', $config['theme']['company']['name'] );
		$this->assertSame( 'mpdf', $config['pdf']['driver'] );

		$html = easy_pdf_word()->template( 'letter', easy_pdf_word()->templates()->get( 'letter' )->sample() )->toHtml();
		$this->assertStringContainsString( '<img', $html, 'The logo is on the document.' );
	}

	public function test_gotenberg_needs_its_address(): void {
		update_option( Settings::OPTION, array( 'engine' => 'gotenberg' ) );
		$this->assertSame( 'mpdf', Documents::config()['pdf']['driver'] );

		update_option(
			Settings::OPTION,
			array(
				'engine'        => 'gotenberg',
				'gotenberg_url' => 'http://gotenberg:3000',
			)
		);
		$config = Documents::config();
		$this->assertSame( 'gotenberg', $config['pdf']['driver'] );
		$this->assertSame( 'http://gotenberg:3000', $config['pdf']['drivers']['gotenberg']['url'] );
		$this->assertSame( 'mpdf', $config['pdf']['fallback'] );
	}

	public function test_sanitize_keeps_only_known_values(): void {
		$settings = SettingsPage::sanitize(
			array(
				'_form'           => '1',
				'company_name'    => '<b>Noor</b>',
				'company_address' => "Line 1\nLine 2<script>x</script>",
				'company_email'   => 'not an email',
				'primary_color'   => 'red; background:url(x)',
				'language'        => 'fr',
				'font'            => '../../etc/passwd',
				'numerals'        => 'arabic',
				'engine'          => 'gotenberg',
				'gotenberg_url'   => 'javascript:alert(1)',
				'number_padding'  => '99',
				'logo'            => '12345',
				'unknown'         => 'x',
			)
		);

		$this->assertSame( 'Noor', $settings['company_name'] );
		$this->assertSame( "Line 1\nLine 2", $settings['company_address'] );
		$this->assertSame( '', $settings['company_email'] );
		$this->assertSame( '#0F766E', $settings['primary_color'] );
		$this->assertSame( '#0F766E', SettingsPage::sanitize( array( 'primary_color' => '' ) )['primary_color'], 'An empty colour is the default.' );
		$this->assertSame( '', $settings['language'] );
		$this->assertSame( 'cairo', $settings['font'] );
		$this->assertSame( 'arabic', $settings['numerals'] );
		$this->assertSame( '', $settings['gotenberg_url'] );
		$this->assertSame( 'mpdf', $settings['engine'], 'No Gotenberg without an address.' );
		$this->assertSame( 12, $settings['number_padding'] );
		$this->assertSame( 0, $settings['logo'], 'Not an image in the media library.' );
		$this->assertArrayNotHasKey( 'unknown', $settings );
		$this->assertArrayNotHasKey( '_form', $settings );
	}

	public function test_an_update_without_the_form_changes_only_what_it_names(): void {
		SettingsPage::register_setting();
		update_option( Settings::OPTION, array( 'company_name' => 'Noor' ) );
		update_option( Settings::OPTION, array_merge( get_option( Settings::OPTION ), array( 'numerals' => 'arabic' ) ) );

		$this->assertSame( 'Noor', Settings::get( 'company_name' ) );
		$this->assertSame( 'arabic', Settings::get( 'numerals' ) );

		update_option( Settings::OPTION, array( 'font' => 'tajawal' ) );
		$this->assertSame( 'Noor', Settings::get( 'company_name' ), 'Kept.' );
		$this->assertSame( 'tajawal', Settings::get( 'font' ) );
	}

	public function test_saving_makes_the_next_document_with_the_new_settings(): void {
		SettingsPage::register();
		$factory = easy_pdf_word();

		update_option( Settings::OPTION, array( 'numerals' => 'arabic' ) );

		$this->assertNotSame( $factory, easy_pdf_word() );
		$this->assertSame( 'arabic', easy_pdf_word()->services()->config( 'numerals' ) );
	}

	public function test_the_page_shows_the_settings_to_those_who_may_change_them(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( Settings::OPTION, array( 'company_name' => 'Noor "& Sons"' ) );

		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'action="' . esc_url( admin_url( 'options.php' ) ) . '"', $html );
		$this->assertStringContainsString( "name='option_page' value='easy_pdf_word'", $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		$this->assertStringContainsString( 'value="Noor &quot;&amp; Sons&quot;"', $html, 'Escaped.' );
		$this->assertStringContainsString( 'easy_pdf_word_settings[font]', $html );
		$this->assertStringContainsString( 'action=easy_pdf_word_preview', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( WPDieException::class );
		SettingsPage::render();
	}

	public function test_the_sample_invoice_needs_the_nonce_and_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( Settings::OPTION, array( 'company_name' => 'شركة النور' ) );

		$_REQUEST['_wpnonce'] = wp_create_nonce( SettingsPage::PREVIEW );
		$file                 = SettingsPage::sample_file();
		$this->assertStringStartsWith( '%PDF', $file->content() );
		$this->assertSame( 'sample-invoice.pdf', $file->filename() );

		$_REQUEST['_wpnonce'] = 'wrong';
		try {
			SettingsPage::sample_file();
			$this->fail( 'A wrong nonce must be refused.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'link you followed has expired', $e->getMessage() );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( SettingsPage::PREVIEW );
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'not allowed' );
		SettingsPage::sample_file();
	}

	public function test_a_settings_link_on_the_plugins_screen(): void {
		$links = SettingsPage::action_links( array( 'deactivate' => 'x' ) );

		$this->assertStringContainsString( 'page=easy-pdf-word', $links[0] );
	}
}
