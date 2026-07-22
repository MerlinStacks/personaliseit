<?php
/**
 * Integration tests for OC_Admin_Settings.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_Admin_Settings extends WP_UnitTestCase {

	/** @var callable|null */
	private $http_mock;

	public function tearDown(): void {
		if ( null !== $this->http_mock ) {
			remove_filter( 'pre_http_request', $this->http_mock, 10 );
			$this->http_mock = null;
		}
		delete_transient( 'oc_openrouter_image_models_v2' );
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
		$this->assertFalse( $by_key['proc_open']['required'] );
		$this->assertFalse( $by_key['ghostscript']['required'] );
		$this->assertFalse( $by_key['ext_imagick']['required'] );
	}
}
