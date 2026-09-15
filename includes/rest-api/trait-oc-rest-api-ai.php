<?php
/**
 * AI image generation, filtering, and result payloads for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API with its authentication, budget, and product helpers. */
trait OC_Rest_API_AI {

	/** Generate prompt-bound artwork for an AI Image layer. */
	public function generate_ai_image( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$body         = $request->get_json_params();
		$body         = is_array( $body ) ? $body : [];
		$product_id   = absint( $body['product_id'] ?? 0 );
		$variation_id = absint( $body['variation_id'] ?? 0 );
		$design_id    = absint( $body['design_id'] ?? 0 );
		$layer_id     = absint( $body['layer_id'] ?? 0 );
		$description  = is_string( $body['description'] ?? null ) ? trim( $body['description'] ) : '';
		if ( ! $product_id || ! $design_id || ! $layer_id ) {
			return new \WP_Error( 'invalid_context', __( 'Product, design, and layer are required.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		if ( '' === $description || strlen( $description ) > 4096 || wp_check_invalid_utf8( $description, true ) !== $description ) {
			return new \WP_Error( 'invalid_description', __( 'Enter a valid image description of up to 4 KiB.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$context = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$layer = self::public_design_layer( $design_id, $layer_id, [ 'ai_image' ] );
		if ( is_wp_error( $layer ) ) {
			return $layer;
		}
		$settings = self::decode_layer_settings( $layer->settings ?? null );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$instruction = is_string( $settings['ai_prompt_instruction'] ?? null ) ? trim( $settings['ai_prompt_instruction'] ) : '';
		if ( '' === $instruction || strlen( $instruction ) > self::MAX_AI_PROMPT_BYTES || wp_check_invalid_utf8( $instruction, true ) !== $instruction ) {
			return new \WP_Error( 'invalid_ai_instruction', __( 'This AI Image layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$ai_config = OC_Admin_Settings::get_ai_image_configuration();
		$api_key   = (string) ( $ai_config['api_key'] ?? '' );
		$model     = trim( (string) ( $ai_config['model'] ?? '' ) );
		$provider  = (string) ( $ai_config['provider'] ?? '' );
		if ( '' === $api_key || trim( $api_key ) !== $api_key || 4096 < strlen( $api_key ) || ! preg_match( '/^[A-Za-z0-9._:-]+$/D', $api_key )
			|| ! array_key_exists( $provider, OC_Admin_Settings::get_ai_image_providers() )
			|| ! preg_match( '#^[A-Za-z0-9._:/-]+$#D', $model ) || 200 < strlen( $model )
		) {
			return new \WP_Error( 'ai_unavailable', __( 'Image generation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$provider_error = OC_AI_Image_Filter::text_generation_provider_error( $provider );
		if ( is_wp_error( $provider_error ) ) {
			return $provider_error;
		}

		$token       = trim( (string) $request->get_header( 'X-OC-Token' ) );
		$token_state = '' !== $token ? self::public_token_state( $token ) : null;
		if ( '' !== $token && null === $token_state ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$token = '';
		}
		$actor            = is_user_logged_in()
			? 'user:' . get_current_user_id()
			: (string) $token_state['binding_type'] . ':' . (string) $token_state['binding_hash'];
		$instruction_hash = hash_hmac( 'sha256', $instruction, wp_salt( 'auth' ) );
		$prompt_hash      = hash_hmac( 'sha256', $instruction . "\0" . $description, wp_salt( 'auth' ) );
		$group            = hash( 'sha256', implode( '|', [ $actor, $prompt_hash, $product_id, $variation_id, $design_id, $layer_id ] ) );
		$attempt_key      = 'oc_ai_image_attempt_' . $group;
		$list_limit       = self::filtered_limit( 'oc_ai_image_list_ip_hourly_limit', 120, 1, 10000 );
		if ( null === $list_limit ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'Image generation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$list_reservation = self::reserve_request_rate( 'ai-image-list', $list_limit, __( 'Too many image generation requests. Please try again later.', 'overcustomise' ) );
		if ( is_wp_error( $list_reservation ) ) {
			return $list_reservation;
		}
		$results = self::ai_image_results( $group, $prompt_hash, $product_id, $variation_id, $design_id, $layer_id, $token );
		if ( ! empty( $body['list_only'] ) ) {
			$attempt_count = max( count( $results ), absint( get_transient( $attempt_key ) ) );
			return rest_ensure_response( array_merge( self::image_filter_result_payload( $results, $attempt_count ), [ 'prompt_hash' => $prompt_hash ] ) );
		}

		$quota_specs = self::ai_generation_quota_specs( $actor );
		if ( is_wp_error( $quota_specs ) ) {
			return $quota_specs;
		}
		$storage_specs = self::upload_capacity_specs( self::MAX_AI_RESULT_BYTES, 1, $token, $token_state, false );
		if ( is_wp_error( $storage_specs ) ) {
			return $storage_specs;
		}
		$lock_key   = 'oc_ai_image_lock_' . $group;
		$lock_owner = self::acquire_option_lock( $lock_key, self::AI_LOCK_TTL );
		if ( is_wp_error( $lock_owner ) ) {
			return new \WP_Error( 'ai_generation_in_progress', __( 'This image is already being generated.', 'overcustomise' ), [ 'status' => 409 ] );
		}

		try {
			$results       = self::ai_image_results( $group, $prompt_hash, $product_id, $variation_id, $design_id, $layer_id, $token );
			$attempt_count = max( count( $results ), absint( get_transient( $attempt_key ) ) );
			if ( $attempt_count >= self::MAX_AI_FILTER_ATTEMPTS ) {
				return new \WP_Error( 'ai_image_attempt_limit', __( 'You have used all generation attempts for this description.', 'overcustomise' ), array_merge( [ 'status' => 429 ], self::image_filter_result_payload( $results, $attempt_count ) ) );
			}
			$storage_reservation = self::reserve_budgets( $storage_specs );
			if ( is_wp_error( $storage_reservation ) ) {
				return $storage_reservation;
			}
			$quota_reservation = self::reserve_budgets( $quota_specs );
			if ( is_wp_error( $quota_reservation ) ) {
				self::release_budget_reservation( $storage_reservation );
				return $quota_reservation;
			}
			$attempt        = $attempt_count + 1;
			$retention_days = max( 1, (int) OC_Admin_Settings::get( 'artwork_retention_days' ) ?: 90 );
			if ( ! set_transient( $attempt_key, $attempt, $retention_days * DAY_IN_SECONDS ) ) {
				self::release_budget_reservation( $storage_reservation );
				self::release_budget_reservation( $quota_reservation );
				return new \WP_Error( 'ai_attempt_unavailable', __( 'Image generation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			try {
				$generated = OC_AI_Image_Filter::generate_from_text( $instruction, $description );
			} catch ( \Throwable $e ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI text-to-image generation threw an exception: ' . $e->getMessage() );
				return new \WP_Error( 'ai_generation_failed', __( 'The image could not be generated. Please try again.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			if ( is_wp_error( $generated ) ) {
				self::release_budget_reservation( $storage_reservation );
				if ( 'ai_provider_role_required' === $generated->get_error_code() ) {
					return $generated;
				}
				$status = 'ai_rate_limited' === $generated->get_error_code() ? 429 : ( 'ai_unavailable' === $generated->get_error_code() ? 503 : 422 );
				return new \WP_Error( 'ai_generation_failed', __( 'The image could not be generated. Please try again.', 'overcustomise' ), [ 'status' => $status ] );
			}
			try {
				$result = OC_Upload_Handler::save_generated_image(
					is_string( $generated['bytes'] ?? null ) ? $generated['bytes'] : '',
					is_string( $generated['mime'] ?? null ) ? $generated['mime'] : '',
					[
						'product_id'   => $product_id,
						'variation_id' => $variation_id,
						'design_id'    => $design_id,
						'layer_id'     => $layer_id,
						'token_hash'   => $token ? hash( 'sha256', $token ) : '',
					],
					[
						'kind'             => 'text_to_image',
						'attempt'          => $attempt,
						'group'            => $group,
						'prompt_hash'      => $prompt_hash,
						'instruction_hash' => $instruction_hash,
						'model'            => (string) ( $generated['model'] ?? $model ),
						'provider'         => (string) ( $generated['provider'] ?? $provider ),
					],
					! empty( $settings['remove_background'] )
				);
			} catch ( \Throwable $e ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image storage threw an exception: ' . $e->getMessage() );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			if ( is_wp_error( $result ) ) {
				self::release_budget_reservation( $storage_reservation );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			$usage        = OC_Upload_Handler::result_attachment_usage( $result );
			$actual_bytes = array_sum( $usage );
			if ( 1 !== count( $usage ) || ! self::reservation_covers_usage( $storage_reservation, 1, $actual_bytes ) || ( '' !== $token && ! self::validate_public_token( $token ) ) ) {
				OC_Upload_Handler::delete_result_attachments( $result );
				self::release_budget_reservation( $storage_reservation );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			if ( ! self::finalise_budget_reservation( $storage_reservation, 1, $actual_bytes ) ) {
				OC_Upload_Handler::delete_result_attachments( $result );
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image storage budget could not be reconciled.' );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$result['source_attachment_id'] = (int) $result['attachment_id'];
			$result['prompt_hash']          = $prompt_hash;
			$result['attempt']              = $attempt;
			$result['attempt_limit']        = self::MAX_AI_FILTER_ATTEMPTS;
			$result['retries_remaining']    = self::MAX_AI_FILTER_ATTEMPTS - $attempt;
			return rest_ensure_response( $result );
		} finally {
			self::delete_owned_option( $lock_key, (string) $lock_owner );
		}
	}

	/** Return generated results for one actor, prompt, and exact layer context. */
	private static function ai_image_results( string $group, string $prompt_hash, int $product_id, int $variation_id, int $design_id, int $layer_id, string $token ): array {
		$ids     = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => [ 'private', 'inherit' ],
				'posts_per_page' => self::MAX_AI_FILTER_ATTEMPTS,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => '_oc_ai_filter_group',
				'meta_value'     => $group,
			]
		);
		$results = [];
		foreach ( array_map( 'absint', is_array( $ids ) ? $ids : [] ) as $attachment_id ) {
			if ( 1 !== (int) get_post_meta( $attachment_id, '_oc_ai_generation', true )
				|| ! hash_equals( $prompt_hash, (string) get_post_meta( $attachment_id, '_oc_ai_prompt_hash', true ) )
				|| ! OC_Upload_Handler::attachment_is_accepted( $attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token )
			) {
				continue;
			}
			$url = OC_Upload_Handler::attachment_access_url( $attachment_id );
			if ( '' === $url ) {
				continue;
			}
			$results[] = [
				'attachment_id'        => $attachment_id,
				'source_attachment_id' => $attachment_id,
				'preview_url'          => $url,
				'original_url'         => $url,
				'file_type'            => sanitize_key( (string) get_post_meta( $attachment_id, '_oc_artwork_type', true ) ),
				'attempt'              => absint( get_post_meta( $attachment_id, '_oc_ai_filter_attempt', true ) ),
				'prompt_hash'          => $prompt_hash,
			];
		}
		usort( $results, static fn ( array $a, array $b ): int => $a['attempt'] <=> $b['attempt'] );
		return $results;
	}

	/** Apply an allowed AI filter and persist the result as owned artwork. */
	public function apply_image_filter( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$body                 = $request->get_json_params();
		$body                 = is_array( $body ) ? $body : [];
		$source_attachment_id = absint( $body['source_attachment_id'] ?? 0 );
		$filter_id            = absint( $body['filter_id'] ?? 0 );
		$layer_id             = absint( $body['layer_id'] ?? 0 );
		$design_id            = absint( $body['design_id'] ?? 0 );
		$product_id           = absint( $body['product_id'] ?? 0 );
		$variation_id         = absint( $body['variation_id'] ?? 0 );
		if ( ! $source_attachment_id || ! $filter_id || ! $layer_id || ! $design_id || ! $product_id ) {
			return new \WP_Error( 'invalid_context', __( 'Image, filter, product, design, and layer are required.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$context = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$layer = self::public_design_layer( $design_id, $layer_id, [ 'image', 'ai_image' ] );
		if ( is_wp_error( $layer ) ) {
			return $layer;
		}
		$settings = self::decode_layer_settings( $layer->settings ?? null );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		if ( ! is_array( $settings['image_filter_ids'] ?? null ) || empty( $settings['image_filter_ids'] ) ) {
			return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$allowed_ids = [];
		foreach ( $settings['image_filter_ids'] as $allowed_id ) {
			$allowed_id = self::setting_positive_int( $allowed_id, PHP_INT_MAX );
			if ( null === $allowed_id ) {
				return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$allowed_ids[] = $allowed_id;
		}
		$allowed_ids = array_values( array_unique( $allowed_ids ) );
		$default_id  = 0;
		if ( array_key_exists( 'default_image_filter_id', $settings ) ) {
			$raw_default = $settings['default_image_filter_id'];
			if ( ! in_array( $raw_default, [ 0, '0' ], true ) ) {
				$default_id = self::setting_positive_int( $raw_default, PHP_INT_MAX );
				if ( null === $default_id ) {
					return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
				}
			}
		}
		$can_change = array_key_exists( 'allow_image_filter_change', $settings ) ? self::setting_boolean( $settings['allow_image_filter_change'] ) : true;
		if ( null === $can_change ) {
			return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$effective_id = $can_change ? $filter_id : $default_id;
		if ( $effective_id !== $filter_id || ! in_array( $filter_id, $allowed_ids, true ) ) {
			return new \WP_Error( 'filter_not_allowed', __( 'This filter is not available for the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$filter = null;
		foreach ( OC_DB::get_image_filters( true ) as $candidate ) {
			if ( (int) $candidate->id === $filter_id ) {
				$filter = $candidate;
				break;
			}
		}
		$prompt = $filter ? trim( (string) ( $filter->prompt ?? '' ) ) : '';
		if ( ! $filter || 'ai' !== (string) $filter->filter_key || '' === $prompt || strlen( $prompt ) > self::MAX_AI_PROMPT_BYTES
			|| wp_check_invalid_utf8( $prompt, true ) !== $prompt
		) {
			return new \WP_Error( 'invalid_filter', __( 'This image effect is unavailable.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$ai_config = OC_Admin_Settings::get_ai_image_configuration();
		$api_key   = (string) ( $ai_config['api_key'] ?? '' );
		$model     = trim( (string) ( $ai_config['model'] ?? '' ) );
		$provider  = (string) ( $ai_config['provider'] ?? '' );
		if ( '' === $api_key || trim( $api_key ) !== $api_key || 4096 < strlen( $api_key ) || ! preg_match( '/^[A-Za-z0-9._:-]+$/D', $api_key )
			|| ! array_key_exists( $provider, OC_Admin_Settings::get_ai_image_providers() )
			|| ! preg_match( '#^[A-Za-z0-9._:/-]+$#D', $model ) || 200 < strlen( $model )
		) {
			OC_Logger::warning( 'AI image filtering was requested with unavailable or malformed provider configuration.' );
			return new \WP_Error( 'ai_unavailable', __( 'Image processing is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$token       = trim( (string) $request->get_header( 'X-OC-Token' ) );
		$token_state = '' !== $token ? self::public_token_state( $token ) : null;
		if ( '' !== $token && null === $token_state ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$token = '';
		}

		$default_attachment_id = 0;
		if ( array_key_exists( 'default_attachment_id', $settings ) && ! in_array( $settings['default_attachment_id'], [ 0, '0', '' ], true ) ) {
			$default_attachment_id = self::setting_positive_int( $settings['default_attachment_id'], PHP_INT_MAX );
			if ( null === $default_attachment_id ) {
				return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
			}
		}
		$source_is_default = $source_attachment_id === $default_attachment_id
			&& OC_Upload_Handler::admin_default_attachment_is_valid( $source_attachment_id )
			&& OC_Upload_Handler::ai_source_is_valid( $source_attachment_id, false );
		$source_is_owned   = ! $source_is_default
			&& OC_Upload_Handler::attachment_is_accepted( $source_attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token )
			&& OC_Upload_Handler::ai_source_is_valid( $source_attachment_id, true );
		if ( ! $source_is_default && ! $source_is_owned ) {
			return new \WP_Error( 'invalid_attachment', __( 'The source image is not valid for this customisation.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$actor       = is_user_logged_in()
			? 'user:' . get_current_user_id()
			: (string) $token_state['binding_type'] . ':' . (string) $token_state['binding_hash'];
		$fingerprint = OC_Upload_Handler::attachment_fingerprint( $source_attachment_id );
		if ( '' === $fingerprint ) {
			return new \WP_Error( 'invalid_attachment', __( 'The source image could not be identified.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$group       = hash(
			'sha256',
			implode(
				'|',
				[
					$actor,
					$fingerprint,
					$product_id,
					$variation_id,
					$design_id,
					$layer_id,
					$filter_id,
				]
			)
		);
		$attempt_key = 'oc_ai_filter_attempt_' . $group;
		$results     = self::image_filter_results( $group, $source_attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token );
		if ( ! empty( $body['list_only'] ) ) {
			$attempt_count = max( count( $results ), absint( get_transient( $attempt_key ) ) );
			return rest_ensure_response( self::image_filter_result_payload( $results, $attempt_count ) );
		}
		$quota_specs = self::ai_quota_specs( $actor );
		if ( is_wp_error( $quota_specs ) ) {
			return $quota_specs;
		}
		$storage_specs = self::upload_capacity_specs( self::MAX_AI_RESULT_BYTES, 1, $token, $token_state, false );
		if ( is_wp_error( $storage_specs ) ) {
			return $storage_specs;
		}

		$lock_key   = 'oc_ai_filter_lock_' . $group;
		$lock_owner = self::acquire_option_lock( $lock_key, self::AI_LOCK_TTL );
		if ( is_wp_error( $lock_owner ) ) {
			return new \WP_Error( 'ai_filter_in_progress', __( 'This image effect is already being applied.', 'overcustomise' ), [ 'status' => 409 ] );
		}

		try {
			$results       = self::image_filter_results( $group, $source_attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token );
			$attempt_count = max( count( $results ), absint( get_transient( $attempt_key ) ) );
			if ( $attempt_count >= self::MAX_AI_FILTER_ATTEMPTS ) {
				return new \WP_Error(
					'ai_filter_attempt_limit',
					__( 'You have used both retries for this image effect.', 'overcustomise' ),
					array_merge( [ 'status' => 429 ], self::image_filter_result_payload( $results, $attempt_count ) )
				);
			}
			$storage_reservation = self::reserve_budgets( $storage_specs );
			if ( is_wp_error( $storage_reservation ) ) {
				return $storage_reservation;
			}
			$quota_reservation = self::reserve_budgets( $quota_specs );
			if ( is_wp_error( $quota_reservation ) ) {
				self::release_budget_reservation( $storage_reservation );
				return $quota_reservation;
			}
			$attempt        = $attempt_count + 1;
			$retention_days = max( 1, (int) OC_Admin_Settings::get( 'artwork_retention_days' ) ?: 90 );
			if ( ! set_transient( $attempt_key, $attempt, $retention_days * DAY_IN_SECONDS ) ) {
				self::release_budget_reservation( $storage_reservation );
				self::release_budget_reservation( $quota_reservation );
				return new \WP_Error( 'ai_attempt_unavailable', __( 'Image processing is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
			}

			try {
				$generated = OC_AI_Image_Filter::generate( $source_attachment_id, $prompt );
			} catch ( \Throwable $e ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI image generation threw an exception after quota reservation: ' . $e->getMessage() );
				return new \WP_Error( 'ai_generation_failed', __( 'The image could not be processed. Please try again.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			if ( is_wp_error( $generated ) ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::warning( 'AI image generation failed after quota reservation: ' . $generated->get_error_code() . ' - ' . $generated->get_error_message() );
				$status = match ( $generated->get_error_code() ) {
					'ai_rate_limited', 'openrouter_rate_limited' => 429,
					'ai_unavailable', 'openrouter_unavailable'   => 503,
					default                   => 422,
				};
				$message = 429 === $status
					? __( 'Image processing is busy. Please try again shortly.', 'overcustomise' )
					: __( 'The image could not be processed. Please try again.', 'overcustomise' );
				return new \WP_Error( 'ai_generation_failed', $message, [ 'status' => $status ] );
			}

			try {
				$result = OC_Upload_Handler::save_generated_image(
					is_string( $generated['bytes'] ?? null ) ? $generated['bytes'] : '',
					is_string( $generated['mime'] ?? null ) ? $generated['mime'] : '',
					[
						'product_id'   => $product_id,
						'variation_id' => $variation_id,
						'design_id'    => $design_id,
						'layer_id'     => $layer_id,
						'token_hash'   => $token ? hash( 'sha256', $token ) : '',
					],
					[
						'source_attachment_id' => $source_attachment_id,
						'filter_id'            => $filter_id,
						'attempt'              => $attempt,
						'group'                => $group,
						'model'                => (string) ( $generated['model'] ?? $model ),
						'provider'             => (string) ( $generated['provider'] ?? $provider ),
					],
					! empty( $filter->remove_background )
				);
			} catch ( \Throwable $e ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image storage threw an exception: ' . $e->getMessage() );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			if ( is_wp_error( $result ) ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image storage failed: ' . $result->get_error_code() . ' - ' . $result->get_error_message() );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}

			$usage        = OC_Upload_Handler::result_attachment_usage( $result );
			$actual_bytes = array_sum( $usage );
			if ( 1 !== count( $usage )
				|| ! self::reservation_covers_usage( $storage_reservation, 1, $actual_bytes )
				|| ( '' !== $token && ! self::validate_public_token( $token ) )
			) {
				OC_Upload_Handler::delete_result_attachments( $result );
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image output exceeded its reservation or lost token ownership.' );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			if ( ! self::finalise_budget_reservation( $storage_reservation, 1, $actual_bytes ) ) {
				OC_Logger::error( 'AI-generated image storage budget could not be reconciled.' );
			}

			$result['source_attachment_id'] = $source_attachment_id;
			$result['filter_id']            = $filter_id;
			$result['attempt']              = $attempt;
			$result['attempt_limit']        = self::MAX_AI_FILTER_ATTEMPTS;
			$result['retries_remaining']    = self::MAX_AI_FILTER_ATTEMPTS - $attempt;
			return rest_ensure_response( $result );
		} finally {
			self::delete_owned_option( $lock_key, (string) $lock_owner );
		}
	}

	/** Return authorised generated results belonging to one source/filter group. */
	private static function image_filter_results( string $group, int $_source_attachment_id, int $product_id, int $variation_id, int $design_id, int $layer_id, string $token ): array {
		$ids     = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => [ 'private', 'inherit' ],
				'posts_per_page' => self::MAX_AI_FILTER_ATTEMPTS,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => '_oc_ai_filter_group',
				'meta_value'     => $group,
			]
		);
		$results = [];
		foreach ( array_map( 'absint', is_array( $ids ) ? $ids : [] ) as $attachment_id ) {
			if ( ! OC_Upload_Handler::attachment_is_accepted( $attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token )
			) {
				continue;
			}
			$result_source_id  = absint( get_post_meta( $attachment_id, '_oc_ai_filter_source_id', true ) );
			$result_source_url = OC_Upload_Handler::attachment_access_url( $result_source_id );
			$url               = OC_Upload_Handler::attachment_access_url( $attachment_id );
			if ( ! $result_source_id || '' === $result_source_url || '' === $url ) {
				continue;
			}
			$results[] = [
				'attachment_id'        => $attachment_id,
				'preview_url'          => $url,
				'original_url'         => $url,
				'file_type'            => sanitize_key( (string) get_post_meta( $attachment_id, '_oc_artwork_type', true ) ),
				'attempt'              => absint( get_post_meta( $attachment_id, '_oc_ai_filter_attempt', true ) ),
				'source_attachment_id' => $result_source_id,
				'source_preview_url'   => $result_source_url,
			];
		}
		usort( $results, static fn ( array $a, array $b ): int => $a['attempt'] <=> $b['attempt'] );
		return $results;
	}

	/** Add attempt limits to a generated-result collection. */
	private static function image_filter_result_payload( array $results, int $attempt_count = 0 ): array {
		$attempts = 0;
		foreach ( $results as $result ) {
			$attempts = max( $attempts, absint( $result['attempt'] ?? 0 ) );
		}
		$attempts = max( $attempts, $attempt_count );
		return [
			'results'           => $results,
			'attempt_limit'     => self::MAX_AI_FILTER_ATTEMPTS,
			'retries_remaining' => max( 0, self::MAX_AI_FILTER_ATTEMPTS - $attempts ),
		];
	}
}
