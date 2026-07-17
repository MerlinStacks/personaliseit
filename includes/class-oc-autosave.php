<?php

defined( 'ABSPATH' ) || exit;

class OC_Autosave {

	private const TRANSIENT_PREFIX = 'oc_autosave_';
	private const TTL_SECONDS      = DAY_IN_SECONDS;
	private const MAX_STATE_BYTES  = 1048576;
	private const MAX_AREAS        = 100;
	private const MAX_LAYERS       = 500;

	public static function store( int $design_id, array $state, int $revision = 0, int $expected_revision = 0 ): array {
		$key = self::key( $design_id );
		if ( null === $key || ! self::is_valid_state( $state ) ) {
			return [
				'status'    => 'invalid',
				'timestamp' => 0,
				'revision'  => 0,
			];
		}

		return self::store_for_key( $key, $state, $revision, $expected_revision );
	}

	private static function store_for_key( string $key, array $state, int $revision, int $expected_revision ): array {
		$expected_revision = max( 0, $expected_revision );
		$revision          = max( 0, $revision );
		$lock_name         = self::acquire_lock( $key );
		if ( null === $lock_name ) {
			return [
				'status'    => 'failed',
				'timestamp' => 0,
				'revision'  => $expected_revision,
			];
		}

		try {
			$current          = get_transient( $key );
			$current_revision = is_array( $current ) ? max( 0, (int) ( $current['revision'] ?? 0 ) ) : 0;
			$current_time     = is_array( $current ) ? (int) ( $current['timestamp'] ?? 0 ) : 0;

			if ( $expected_revision !== $current_revision ) {
				return [
					'status'    => 'conflict',
					'timestamp' => $current_time,
					'revision'  => $current_revision,
				];
			}

			if ( $revision !== $expected_revision + 1 ) {
				return [
					'status'    => 'invalid',
					'timestamp' => $current_time,
					'revision'  => $current_revision,
				];
			}

			$timestamp = time();
			$data = [
				'state'     => $state,
				'timestamp' => $timestamp,
				'revision'  => $revision,
			];
			if ( false === set_transient( $key, $data, self::TTL_SECONDS ) ) {
				return [
					'status'    => 'failed',
					'timestamp' => $current_time,
					'revision'  => $current_revision,
				];
			}

			return [
				'status'    => 'stored',
				'timestamp' => $timestamp,
				'revision'  => $revision,
			];
		} finally {
			self::release_lock( $lock_name );
		}
	}

	public static function restore( int $design_id ): ?array {
		$key = self::key( $design_id );
		if ( null === $key ) {
			return null;
		}
		$lock_name = self::acquire_lock( $key );
		if ( null === $lock_name ) {
			return null;
		}

		try {
			$data = get_transient( $key );
			if ( false === $data || ! is_array( $data ) || ! isset( $data['state'] ) || ! is_array( $data['state'] ) || ! self::is_valid_state( $data['state'] ) ) {
				return null;
			}
			set_transient( $key, $data, self::TTL_SECONDS );
			return [
				'state'     => $data['state'],
				'timestamp' => (int) ( $data['timestamp'] ?? 0 ),
				'revision'  => (int) ( $data['revision'] ?? 0 ),
			];
		} finally {
			self::release_lock( $lock_name );
		}
	}

	public static function clear( int $design_id ): bool {
		$key = self::key( $design_id, false );
		if ( null === $key ) {
			return false;
		}
		$lock_name = self::acquire_lock( $key );
		if ( null === $lock_name ) {
			return false;
		}

		try {
			return delete_transient( $key );
		} finally {
			self::release_lock( $lock_name );
		}
	}

	/** Serialize read/compare/write cycles across concurrent admin requests. */
	private static function acquire_lock( string $key ): ?string {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return null;
		}

		$lock_name = 'oc_autosave_' . sha1( $key );
		try {
			$acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 1)', $lock_name ) );
		} catch ( \Throwable $e ) {
			return null;
		}

		return 1 === $acquired ? $lock_name : null;
	}

	/** Release only the advisory lock acquired by this request. */
	private static function release_lock( string $lock_name ): void {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return;
		}

		try {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		} catch ( \Throwable $e ) {
			// MySQL releases advisory locks when the connection closes.
		}
	}

	private static function key( int $design_id, bool $require_design = true ): ?string {
		$user_id = get_current_user_id();
		if ( $design_id <= 0 || $user_id <= 0 || ( $require_design && ! OC_DB::get_design( $design_id ) ) ) {
			return null;
		}

		return self::TRANSIENT_PREFIX . $design_id . '_' . $user_id;
	}

	private static function is_valid_state( array $state ): bool {
		if (
			2 !== count( $state )
			|| ! isset( $state['design'], $state['areas'] )
			|| ! is_array( $state['design'] )
			|| ! is_array( $state['areas'] )
			|| count( $state['areas'] ) > self::MAX_AREAS
		) {
			return false;
		}

		$design = $state['design'];
		if (
			! isset( $design['name'], $design['customType'], $design['flatRate'], $design['active'] )
			|| ! is_string( $design['name'] )
			|| ! in_array( $design['customType'], [ 'text_only', 'photo_text' ], true )
			|| ! is_numeric( $design['flatRate'] )
			|| (float) $design['flatRate'] < 0
			|| ! is_bool( $design['active'] )
		) {
			return false;
		}

		$layer_count = 0;
		foreach ( $state['areas'] as $area ) {
			if (
				! is_array( $area )
				|| ! isset( $area['unit'], $area['layers'] )
				|| ! in_array( $area['unit'], [ 'px', 'mm', 'cm', 'in' ], true )
				|| ! is_array( $area['layers'] )
			) {
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
