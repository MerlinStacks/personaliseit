<?php
/**
 * Artwork upload and context authorisation for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API with its authentication, budget, and product helpers. */
trait OC_Rest_API_Artwork {

	/** Handle customer artwork upload. */
	public function upload_artwork( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$files = $request->get_file_params();
		if ( empty( $files['artwork'] ) || ! is_array( $files['artwork'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file received.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$layer_id     = absint( $request->get_param( 'layer_id' ) );
		$design_id    = absint( $request->get_param( 'design_id' ) );
		$product_id   = absint( $request->get_param( 'product_id' ) );
		$variation_id = absint( $request->get_param( 'variation_id' ) );
		if ( ! $layer_id || ! $design_id || ! $product_id ) {
			return new \WP_Error( 'invalid_context', __( 'Product, design, and layer are required for artwork uploads.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$context = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$layer = self::public_design_layer( $design_id, $layer_id, [ 'image', 'clipmask' ] );
		if ( is_wp_error( $layer ) ) {
			return $layer;
		}
		$layer_overrides = self::upload_layer_overrides( $layer );
		if ( is_wp_error( $layer_overrides ) ) {
			return $layer_overrides;
		}

		$token       = trim( (string) $request->get_header( 'X-OC-Token' ) );
		$token_state = '' !== $token ? self::public_token_state( $token ) : null;
		if ( '' !== $token && null === $token_state ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$token = '';
		}

		try {
			$inspection = OC_Upload_Handler::inspect_upload( $files['artwork'], $layer_overrides );
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Artwork upload validation failed: ' . $e->getMessage() );
			return new \WP_Error( 'upload_failed', __( 'The artwork file could not be accepted. Please check it and try again.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		$specs = self::upload_capacity_specs(
			(int) $inspection['reservation_bytes'],
			(int) $inspection['attachment_count'],
			$token,
			$token_state,
			true
		);
		if ( is_wp_error( $specs ) ) {
			return $specs;
		}
		$reservation = self::reserve_budgets( $specs );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}

		try {
			$result = OC_Upload_Handler::process(
				$files['artwork'],
				$layer_overrides,
				[
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'design_id'    => $design_id,
					'layer_id'     => $layer_id,
					'token_hash'   => $token ? hash( 'sha256', $token ) : '',
				]
			);
		} catch ( \Throwable $e ) {
			self::finalise_budget_reservation( $reservation, 0, 0 );
			OC_Logger::warning( 'Artwork upload failed: ' . $e->getMessage() );
			return new \WP_Error( 'upload_failed', __( 'The artwork file could not be processed. Please check it and try again.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		$usage            = OC_Upload_Handler::result_attachment_usage( $result );
		$attachment_count = count( $usage );
		$actual_bytes     = array_sum( $usage );
		if ( empty( $usage )
			|| ! self::reservation_covers_usage( $reservation, $attachment_count, $actual_bytes )
			|| ( '' !== $token && ! self::validate_public_token( $token ) )
		) {
			OC_Upload_Handler::delete_result_attachments( $result );
			self::finalise_budget_reservation( $reservation, 0, 0 );
			OC_Logger::error( 'Artwork upload output exceeded its reservation or lost token ownership before commit.' );
			return new \WP_Error( 'upload_failed', __( 'The artwork file could not be retained safely. Please try again.', 'overcustomise' ), [ 'status' => 422 ] );
		}
		if ( ! self::finalise_budget_reservation( $reservation, $attachment_count, $actual_bytes ) ) {
			// The conservative reservation remains charged if reconciliation fails.
			OC_Logger::error( 'Artwork upload budget could not be reconciled to actual stored bytes.' );
		}

		return rest_ensure_response( $result );
	}

	/** Authorise an owned image for a matching link group in another product context. */
	public function authorise_artwork_context( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$body                     = $request->get_json_params();
		$body                     = is_array( $body ) ? $body : [];
		$source_attachment_id     = absint( $body['source_attachment_id'] ?? 0 );
		$derivative_attachment_id = absint( $body['derivative_attachment_id'] ?? 0 );
		$product_id               = absint( $body['product_id'] ?? 0 );
		$variation_id             = absint( $body['variation_id'] ?? 0 );
		$design_id                = absint( $body['design_id'] ?? 0 );
		$layer_id                 = absint( $body['layer_id'] ?? 0 );
		if ( ! $source_attachment_id || ! $product_id || ! $design_id || ! $layer_id ) {
			return new \WP_Error( 'invalid_context', __( 'Image, product, design, and layer are required.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$limit = self::filtered_limit( 'oc_artwork_authorisation_ip_hourly_limit', 120, 1, 10000 );
		if ( null === $limit ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$reservation = self::reserve_request_rate( 'artwork-authorisation', $limit, __( 'Too many image reuse requests. Please try again later.', 'overcustomise' ) );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}

		$destination = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}
		$target_layer = self::public_design_layer( $design_id, $layer_id, [ 'image', 'ai_image', 'clipmask' ] );
		if ( is_wp_error( $target_layer ) ) {
			return $target_layer;
		}
		$target_policy = self::upload_layer_overrides( $target_layer );
		if ( is_wp_error( $target_policy ) ) {
			return $target_policy;
		}

		$source_context = OC_Upload_Handler::attachment_primary_context( $source_attachment_id );
		$token          = trim( (string) $request->get_header( 'X-OC-Token' ) );
		if ( null === $source_context || $source_context[0] !== $product_id
			|| ! OC_Upload_Handler::attachment_is_accepted( $source_attachment_id, ...array_merge( $source_context, [ $token ] ) )
			|| ! OC_Upload_Handler::attachment_matches_upload_policy( $source_attachment_id, $target_policy, false )
		) {
			return new \WP_Error( 'invalid_attachment', __( 'The image is not valid for this customisation.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$source_assignment = self::active_assignment_design( $source_context[0], $source_context[1], $source_context[2] );
		$source_layer      = is_wp_error( $source_assignment ) ? $source_assignment : self::public_design_layer( $source_context[2], $source_context[3], [ 'image', 'ai_image', 'clipmask' ] );
		if ( is_wp_error( $source_layer ) || (string) $source_layer->type !== (string) $target_layer->type ) {
			return new \WP_Error( 'invalid_link_group', __( 'This image cannot be shared with the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$source_settings = self::decode_layer_settings( $source_layer->settings ?? null );
		$target_settings = self::decode_layer_settings( $target_layer->settings ?? null );
		if ( is_wp_error( $source_settings ) || is_wp_error( $target_settings ) ) {
			return is_wp_error( $source_settings ) ? $source_settings : $target_settings;
		}
		$source_group = sanitize_key( (string) ( $source_settings['link_group'] ?? '' ) );
		$target_group = sanitize_key( (string) ( $target_settings['link_group'] ?? '' ) );
		if ( '' === $source_group || $source_group !== $target_group ) {
			return new \WP_Error( 'invalid_link_group', __( 'This image cannot be shared with the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$source_policy = self::upload_layer_overrides( $source_layer );
		if ( is_wp_error( $source_policy ) || (bool) $source_policy['remove_background'] !== (bool) $target_policy['remove_background'] ) {
			return new \WP_Error( 'incompatible_image_processing', __( 'This image must be uploaded separately for the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( $derivative_attachment_id && $derivative_attachment_id !== $source_attachment_id ) {
			$derivative_context = OC_Upload_Handler::attachment_primary_context( $derivative_attachment_id );
			$filter_id          = absint( get_post_meta( $derivative_attachment_id, '_oc_ai_filter_id', true ) );
			$allowed_filters    = array_values( array_filter( array_map( 'absint', (array) ( $target_settings['image_filter_ids'] ?? [] ) ) ) );
			$can_change_filter  = ! array_key_exists( 'allow_image_filter_change', $target_settings ) || true === self::setting_boolean( $target_settings['allow_image_filter_change'] );
			$default_filter_id  = absint( $target_settings['default_image_filter_id'] ?? 0 );
			$filter_permitted   = $can_change_filter || $default_filter_id === $filter_id;
			$active_ai_filter   = false;
			foreach ( OC_DB::get_image_filters( true ) as $filter ) {
				if ( (int) $filter->id === $filter_id && 'ai' === (string) $filter->filter_key ) {
					$active_ai_filter = true;
					break;
				}
			}
			if ( ! in_array( (string) $target_layer->type, [ 'image', 'ai_image' ], true ) || null === $derivative_context || $derivative_context[0] !== $product_id
				|| ! OC_Upload_Handler::attachment_is_accepted( $derivative_attachment_id, ...array_merge( $derivative_context, [ $token ] ) )
				|| ! OC_Upload_Handler::attachment_matches_upload_policy( $derivative_attachment_id, $target_policy, false )
				|| 1 !== (int) get_post_meta( $derivative_attachment_id, '_oc_ai_filter', true )
				|| $source_attachment_id !== absint( get_post_meta( $derivative_attachment_id, '_oc_ai_filter_source_id', true ) )
				|| ! $filter_id || ! $filter_permitted || ! in_array( $filter_id, $allowed_filters, true ) || ! $active_ai_filter
			) {
				return new \WP_Error( 'invalid_attachment', __( 'The filtered image is not valid for the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
			}
		}

		$target_context = [ $product_id, $variation_id, $design_id, $layer_id ];
		if ( ! OC_Upload_Handler::authorise_attachment_context( $source_attachment_id, $target_context ) ) {
			return new \WP_Error( 'authorisation_failed', __( 'The image could not be prepared for this design.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( $derivative_attachment_id && $derivative_attachment_id !== $source_attachment_id
			&& ! OC_Upload_Handler::authorise_attachment_context( $derivative_attachment_id, $target_context )
		) {
			return new \WP_Error( 'authorisation_failed', __( 'The filtered image could not be prepared for this design.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		return rest_ensure_response(
			[
				'source_attachment_id'     => $source_attachment_id,
				'derivative_attachment_id' => $derivative_attachment_id,
				'context'                  => [
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'design_id'    => $design_id,
					'layer_id'     => $layer_id,
				],
			]
		);
	}
}
