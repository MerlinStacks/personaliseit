<?php
/**
 * Integration tests for OC_Admin_Settings.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

require_once OC_PATH . 'includes/admin/class-oc-admin-print-methods.php';

class Test_Admin_Settings extends WP_UnitTestCase {

	/** @var callable|null */
	private $http_mock;

	public function tearDown(): void {
		if ( null !== $this->http_mock ) {
			remove_filter( 'pre_http_request', $this->http_mock, 10 );
			$this->http_mock = null;
		}
		delete_transient( 'oc_openrouter_image_models_v2' );
		delete_transient( 'oc_google_image_models_v1' );
		delete_transient( 'oc_openai_image_models_v1' );
		parent::tearDown();
	}

	#[Test]
	public function model_chooser_only_returns_models_that_accept_and_generate_images(): void {
		$this->http_mock = static function ( $preempt, array $args, string $url ): array|false {
			if ( 'https://openrouter.ai/api/v1/models' !== $url ) {
				return false;
			}

			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [
					'data' => [
						[
							'id'           => 'vendor/image-editor',
							'name'         => 'Image Editor',
							'architecture' => [
								'input_modalities'  => [ 'text', 'image' ],
								'output_modalities' => [ 'text', 'image' ],
							],
						],
						[
							'id'           => 'vendor/vision-model',
							'name'         => 'Vision Model',
							'architecture' => [
								'input_modalities'  => [ 'text', 'image' ],
								'output_modalities' => [ 'text' ],
							],
						],
						[
							'id'           => 'vendor/text-to-image',
							'name'         => 'Text to Image Model',
							'architecture' => [
								'input_modalities'  => [ 'text' ],
								'output_modalities' => [ 'image' ],
							],
						],
						[
							'id'           => 'vendor/image-name-only',
							'name'         => 'Not an Image Model',
							'architecture' => [
								'input_modalities'  => [ 'text' ],
								'output_modalities' => [ 'text' ],
							],
						],
					],
				] ),
			];
		};
		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );

		$models = OC_Admin_Settings::get_openrouter_image_models( true );

		$this->assertSame( [ 'vendor/image-editor' => 'Image Editor' ], $models );
	}

	#[Test]
	public function system_status_includes_production_runtime_dependencies(): void {
		$checks = OC_System_Status::checks();
		$by_key = array_column( $checks, null, 'key' );

		$this->assertArrayHasKey( 'tcpdf', $by_key );
		$this->assertArrayHasKey( 'proc_open', $by_key );
		$this->assertArrayHasKey( 'ghostscript', $by_key );
		$this->assertArrayHasKey( 'ext_imagick', $by_key );
		$this->assertArrayHasKey( 'imagick_heic', $by_key );
		$this->assertFalse( $by_key['proc_open']['required'] );
		$this->assertFalse( $by_key['ghostscript']['required'] );
		$this->assertFalse( $by_key['ext_imagick']['required'] );
		$this->assertFalse( $by_key['imagick_heic']['required'] );
	}

	#[Test]
	public function customer_file_retention_is_independent_from_print_file_retention(): void {
		update_option( 'oc_settings', [ 'file_retention_days' => 7 ] );
		try {
			$this->assertSame( 7, OC_Admin_Settings::get( 'file_retention_days' ) );
			$this->assertSame( 90, OC_Admin_Settings::get( 'preview_retention_days' ) );
			$this->assertSame( 90, OC_Admin_Settings::get( 'artwork_retention_days' ) );
		} finally {
			delete_option( 'oc_settings' );
		}
	}

	#[Test]
	public function normalised_settings_cache_observes_writes_in_the_same_request(): void {
		update_option( 'oc_settings', [ 'file_retention_days' => 7 ] );
		$this->assertSame( 7, OC_Admin_Settings::get( 'file_retention_days' ) );

		update_option( 'oc_settings', [ 'file_retention_days' => 30 ] );
		$this->assertSame( 30, OC_Admin_Settings::get( 'file_retention_days' ) );
	}

	#[Test]
	public function existing_install_defaults_to_openrouter_provider(): void {
		update_option( 'oc_settings', [ 'openrouter_image_model' => 'google/gemini-2.5-flash-image' ] );
		try {
			$this->assertSame( 'openrouter', OC_Admin_Settings::get_ai_image_provider() );
			$this->assertSame( 'openrouter', OC_Admin_Settings::get_ai_image_configuration()['provider'] );
		} finally {
			delete_option( 'oc_settings' );
		}
	}

	#[Test]
	public function direct_provider_models_are_available_without_remote_discovery(): void {
		$google_models = OC_Admin_Settings::get_ai_image_models( 'google' );
		$openai_models = OC_Admin_Settings::get_ai_image_models( 'openai' );

		$this->assertSame( 'gemini-3.1-flash-image', array_key_first( $google_models ) );
		$this->assertArrayHasKey( 'gemini-3-pro-image', $google_models );
		$this->assertArrayHasKey( 'gemini-3.1-flash-lite-image', $google_models );
		$this->assertArrayHasKey( 'gemini-2.5-flash-image', $google_models );
		$this->assertSame( 'gpt-image-2', array_key_first( $openai_models ) );
		$this->assertArrayHasKey( 'gpt-image-1.5', $openai_models );
		$this->assertArrayHasKey( 'gpt-image-1', $openai_models );
		$this->assertArrayHasKey( 'gpt-image-1-mini', $openai_models );
	}

	#[Test]
	public function google_discovery_only_keeps_image_generation_models(): void {
		$method = ( new ReflectionClass( OC_Admin_Settings::class ) )->getMethod( 'parse_google_image_models' );
		$result = $method->invoke(
			null,
			[
				'models' => [
					[
						'name'                       => 'models/gemini-3.1-flash-image',
						'displayName'                => 'Gemini 3.1 Flash Image',
						'supportedGenerationMethods' => [ 'generateContent' ],
					],
					[
						'name'                       => 'models/gemini-4-flash-image',
						'displayName'                => 'Gemini 4 Flash Image',
						'supportedGenerationMethods' => [ 'generateContent' ],
					],
					[
						'name'                       => 'models/gemini-3.1-flash',
						'displayName'                => 'Vision only',
						'supportedGenerationMethods' => [ 'generateContent' ],
					],
					[
						'name'                       => 'models/gemini-old-image',
						'displayName'                => 'No generation method',
						'supportedGenerationMethods' => [ 'countTokens' ],
					],
				],
			]
		);

		$this->assertSame( [ 'gemini-3.1-flash-image', 'gemini-4-flash-image' ], array_keys( $result ) );
	}

	#[Test]
	public function openai_discovery_keeps_stable_gpt_image_aliases_only(): void {
		$method = ( new ReflectionClass( OC_Admin_Settings::class ) )->getMethod( 'parse_openai_image_models' );
		$result = $method->invoke(
			null,
			[
				'data' => [
					[ 'id' => 'gpt-image-2' ],
					[ 'id' => 'gpt-image-2-mini' ],
					[ 'id' => 'gpt-image-2-2026-04-21' ],
					[ 'id' => 'gpt-5.6' ],
				],
			]
		);

		$this->assertSame( [ 'gpt-image-2', 'gpt-image-2-mini' ], array_keys( $result ) );
	}

	#[Test]
	public function normalised_print_method_cache_observes_writes_in_the_same_request(): void {
		update_option( 'oc_print_methods', [ 'uv' => [ 'dpi' => 300 ] ] );
		$this->assertSame( 300, OC_Admin_Print_Methods::get( 'uv' )['dpi'] );

		update_option( 'oc_print_methods', [ 'uv' => [ 'dpi' => 600 ] ] );
		$this->assertSame( 600, OC_Admin_Print_Methods::get( 'uv' )['dpi'] );
	}
}
