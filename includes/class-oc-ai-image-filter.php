<?php
/**
 * OpenRouter image-to-image filter client.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_AI_Image_Filter {

	private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
	private const MAX_RESULT_BYTES = 15728640;

	/** Generate a filtered image from a raster attachment. */
	public static function generate( int $source_attachment_id, string $prompt ): array|\WP_Error {
		$api_key = OC_Admin_Settings::get_openrouter_api_key();
		if ( '' === $api_key ) {
			return new \WP_Error( 'openrouter_not_configured', __( 'OpenRouter is not configured. Ask the store administrator to add an API key.', 'overcustomise' ) );
		}

		$prompt = trim( $prompt );
		if ( '' === $prompt ) {
			return new \WP_Error( 'invalid_filter', __( 'This AI filter has no prompt.', 'overcustomise' ) );
		}

		$path = get_attached_file( $source_attachment_id );
		$mime = (string) get_post_mime_type( $source_attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) || ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
			return new \WP_Error( 'unsupported_source', __( 'AI filters currently require a JPG, PNG, or WebP image.', 'overcustomise' ) );
		}

		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return new \WP_Error( 'source_unreadable', __( 'The uploaded image could not be read.', 'overcustomise' ) );
		}

		$model    = OC_Admin_Settings::get_openrouter_image_model();
		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout'     => 120,
				'redirection' => 0,
				'headers'     => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'OverCustomise',
				],
				'body'        => wp_json_encode( [
					'model'      => $model,
					'modalities' => [ 'image', 'text' ],
					'messages'   => [
						[
							'role'    => 'user',
							'content' => [
								[ 'type' => 'text', 'text' => $prompt ],
								[ 'type' => 'image_url', 'image_url' => [ 'url' => 'data:' . $mime . ';base64,' . base64_encode( $bytes ) ] ],
							],
						],
					],
				] ),
			],
		);

		if ( is_wp_error( $response ) ) {
			OC_Logger::warning( 'OpenRouter image request failed: ' . $response->get_error_message() );
			return new \WP_Error( 'openrouter_unavailable', __( 'The AI image service could not be reached. Please try again.', 'overcustomise' ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $body ) ? sanitize_text_field( (string) ( $body['error']['message'] ?? '' ) ) : '';
			OC_Logger::warning( sprintf( 'OpenRouter image request returned HTTP %d: %s', $status, $message ) );
			if ( 429 === $status ) {
				return new \WP_Error( 'openrouter_rate_limited', __( 'The AI image service is busy. Please try again shortly.', 'overcustomise' ) );
			}
			return new \WP_Error( 'openrouter_failed', $message ?: __( 'The AI image service could not process this image.', 'overcustomise' ) );
		}

		$image = self::extract_image( is_array( $body ) ? $body : [] );
		if ( is_wp_error( $image ) ) {
			OC_Logger::warning( 'OpenRouter returned no usable image.' );
			return $image;
		}

		$image['model'] = $model;
		return $image;
	}

	/** Decode the image returned by OpenRouter's supported response shapes. */
	private static function extract_image( array $body ): array|\WP_Error {
		$message = $body['choices'][0]['message'] ?? [];
		$urls    = [];

		foreach ( is_array( $message['images'] ?? null ) ? $message['images'] : [] as $image ) {
			if ( is_array( $image ) ) {
				$urls[] = $image['image_url']['url'] ?? $image['url'] ?? '';
			}
		}
		foreach ( is_array( $message['content'] ?? null ) ? $message['content'] : [] as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}
			$urls[] = $part['image_url']['url'] ?? $part['image_url'] ?? '';
			if ( ! empty( $part['b64_json'] ) ) {
				$urls[] = 'data:image/png;base64,' . $part['b64_json'];
			}
		}

		foreach ( array_filter( $urls, 'is_string' ) as $url ) {
			$decoded = self::decode_image_url( $url );
			if ( ! is_wp_error( $decoded ) ) {
				return $decoded;
			}
		}

		return new \WP_Error( 'invalid_ai_response', __( 'The AI model did not return an image. Try another image model.', 'overcustomise' ) );
	}

	/** Decode a data URL, or download a provider-hosted HTTPS result. */
	private static function decode_image_url( string $url ): array|\WP_Error {
		$mime  = '';
		$bytes = false;
		if ( preg_match( '#^data:(image/(?:png|jpeg|webp));base64,(.+)$#s', $url, $matches ) ) {
			$mime  = strtolower( $matches[1] );
			$bytes = base64_decode( $matches[2], true );
		} elseif ( wp_http_validate_url( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			$response = wp_safe_remote_get( $url, [ 'timeout' => 60, 'limit_response_size' => self::MAX_RESULT_BYTES ] );
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$mime  = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
				$bytes = wp_remote_retrieve_body( $response );
			}
		}

		if ( ! is_string( $bytes ) || '' === $bytes || strlen( $bytes ) > self::MAX_RESULT_BYTES ) {
			return new \WP_Error( 'invalid_ai_image', __( 'The AI model returned invalid image data.', 'overcustomise' ) );
		}
		$info = @getimagesizefromstring( $bytes );
		if ( ! is_array( $info ) || ! in_array( (string) ( $info['mime'] ?? '' ), [ 'image/png', 'image/jpeg', 'image/webp' ], true ) ) {
			return new \WP_Error( 'invalid_ai_image', __( 'The AI model returned an unsupported image.', 'overcustomise' ) );
		}

		return [ 'bytes' => $bytes, 'mime' => (string) $info['mime'] ];
	}
}
