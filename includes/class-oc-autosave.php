<?php

defined( 'ABSPATH' ) || exit;

class OC_Autosave {

	private const TRANSIENT_PREFIX = 'oc_autosave_';
	private const TTL_SECONDS      = 300;
	private const MAX_STATE_BYTES  = 1048576;
	private const MAX_AREAS        = 100;
	private const MAX_LAYERS       = 500;

	public static function store( int $design_id, array $state, int $revision = 0 ): bool {
		$key = self::key( $design_id );
		if ( null === $key || ! self::is_valid_state( $state ) ) {
			return false;
		}
		$data = [
			'state'     => $state,
			'timestamp' => time(),
			'revision'  => max( 0, $revision ),
		];
		return set_transient( $key, $data, self::TTL_SECONDS ) !== false;
	}

	public static function restore( int $design_id ): ?array {
		$key = self::key( $design_id );
		if ( null === $key ) {
			return null;
		}
		$data = get_transient( $key );
		if ( false === $data || ! is_array( $data ) || ! isset( $data['state'] ) || ! is_array( $data['state'] ) || ! self::is_valid_state( $data['state'] ) ) {
			return null;
		}
		return [
			'state'     => $data['state'],
			'timestamp' => (int) ( $data['timestamp'] ?? 0 ),
			'revision'  => (int) ( $data['revision'] ?? 0 ),
		];
	}

	public static function clear( int $design_id ): bool {
		$key = self::key( $design_id, false );
		if ( null === $key ) {
			return false;
		}
		return delete_transient( $key );
	}

	private static function key( int $design_id, bool $require_design = true ): ?string {
		$user_id = get_current_user_id();
		if ( $design_id <= 0 || $user_id <= 0 || ( $require_design && ! OC_DB::get_design( $design_id ) ) ) {
			return null;
		}

		return self::TRANSIENT_PREFIX . $design_id . '_' . $user_id;
	}

	private static function is_valid_state( array $state ): bool {
		if ( array_keys( $state ) !== [ 'areas' ] || ! is_array( $state['areas'] ) || count( $state['areas'] ) > self::MAX_AREAS ) {
			return false;
		}

		$layer_count = 0;
		foreach ( $state['areas'] as $area ) {
			if ( ! is_array( $area ) || ! isset( $area['layers'] ) || ! is_array( $area['layers'] ) ) {
				return false;
			}
			foreach ( $area['layers'] as $layer ) {
				if ( ! is_array( $layer ) || ( isset( $layer['settings'] ) && ! is_array( $layer['settings'] ) ) ) {
					return false;
				}
			}
			$layer_count += count( $area['layers'] );
			if ( $layer_count > self::MAX_LAYERS ) {
				return false;
			}
		}

		$encoded = wp_json_encode( $state );
		return is_string( $encoded ) && strlen( $encoded ) <= self::MAX_STATE_BYTES;
	}
}
