<?php

defined( 'ABSPATH' ) || exit;

class OC_Autosave {

	private const TRANSIENT_PREFIX           = 'oc_autosave_';
	private const TTL_SECONDS                = DAY_IN_SECONDS;
	private const MAX_STATE_BYTES            = 1048576;
	private const MAX_AREAS                  = 100;
	private const MAX_LAYERS                 = 1000;
	private const RECOVERY_PREFIX            = 'oc_recovery_';
	private const RECOVERY_RETENTION_SECONDS = 30 * DAY_IN_SECONDS;

	/** Separate, user-scoped recovery copies cannot be replaced by another tab. */
	public static function retain_recovery( array $data ): ?string {
		$json = wp_json_encode( $data );
		if ( get_current_user_id() <= 0 || ! is_string( $json ) || strlen( $json ) > self::MAX_STATE_BYTES ) {
			return null;
		}
		$token    = hash( 'sha256', $json );
		$key      = self::RECOVERY_PREFIX . get_current_user_id() . '_' . $token;
		$existing = self::recovery_record( $key );
		if ( is_array( $existing ) && $existing['data'] === $data && $existing['expires_at'] > time() ) {
			return $token;
		}
		$record = [
			'version'    => 1,
			'expires_at' => time() + self::RECOVERY_RETENTION_SECONDS,
			'data'       => $data,
		];
		$stored = null === $existing
			? add_option( $key, $record, '', false )
			: update_option( $key, $record, false );
		if ( $stored || get_option( $key, null ) === $record ) {
			return $token;
		}
		return null;
	}

	public static function recovery( string $token ): ?array {
		if ( get_current_user_id() <= 0 || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			return null;
		}
		$record = self::recovery_record( self::RECOVERY_PREFIX . get_current_user_id() . '_' . $token );
		return is_array( $record ) && $record['expires_at'] > time() ? $record['data'] : null;
	}

	/** Preserve old immutable payloads, granting them a full retention window on upgrade. */
	private static function recovery_record( string $key ): ?array {
		$record = get_option( $key, null );
		if ( ! is_array( $record ) ) {
			return null;
		}
		if ( ( isset( $record['state'] ) && is_array( $record['state'] ) ) || 'overcustomise-rejected-form' === ( $record['format'] ?? null ) ) {
			$record = [
				'version'    => 1,
				'expires_at' => time() + self::RECOVERY_RETENTION_SECONDS,
				'data'       => $record,
			];
			if ( ! update_option( $key, $record, false ) && get_option( $key, null ) !== $record ) {
				return null;
			}
		}
		return 1 === ( $record['version'] ?? null ) && is_int( $record['expires_at'] ?? null ) && is_array( $record['data'] ?? null )
			? $record : null;
	}

	/** Expire recovery copies in bounded daily batches; ordinary reads never extend retention. */
	public static function cleanup_recovery(): void {
		global $wpdb;
		$cursor = max( 0, (int) get_option( 'oc_recovery_cleanup_cursor', 0 ) );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id ASC LIMIT 100",
				$wpdb->esc_like( self::RECOVERY_PREFIX ) . '%',
				$cursor
			)
		);
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return;
		}
		foreach ( $rows as $row ) {
			$key = (string) $row->option_name;
			if ( ! preg_match( '/^oc_recovery_[1-9][0-9]*_[a-f0-9]{64}$/D', $key ) ) {
				continue;
			}
			$record = self::recovery_record( $key );
			if ( null === $record || $record['expires_at'] > time() ) {
				continue;
			}
			// An explicit retain can renew an expired copy while this worker runs.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
					$key,
					maybe_serialize( $record )
				)
			);
			if ( 1 === $deleted ) {
				wp_cache_delete( $key, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
			}
		}
		update_option( 'oc_recovery_cleanup_cursor', count( $rows ) < 100 ? 0 : (int) end( $rows )->option_id, false );
	}

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
			if ( $revision === $expected_revision + 1 && $revision === $current_revision && ( $current['state'] ?? null ) === $state ) {
				return [
					'status'    => 'stored',
					'timestamp' => $current_time,
					'revision'  => $current_revision,
				];
			}

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
			if ( is_array( $current ) && empty( $current['state']['design']['baseRevision'] ) && ! self::retain_recovery( $current ) ) {
				return [ 'status' => 'failed', 'timestamp' => $current_time, 'revision' => $current_revision ];
			}
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
			$recovery = self::retain_recovery( $data );
			return [
				'recoveryToken' => $recovery,
				'state'     => $data['state'],
				'timestamp' => (int) ( $data['timestamp'] ?? 0 ),
				'revision'  => (int) ( $data['revision'] ?? 0 ),
			];
		} finally {
			self::release_lock( $lock_name );
		}
	}

	public static function clear( int $design_id, ?int $expected_revision = null ): bool {
		$key = self::key( $design_id, false );
		if ( null === $key ) {
			return false;
		}
		$lock_name = self::acquire_lock( $key );
		if ( null === $lock_name ) {
			return false;
		}

		try {
			$current = get_transient( $key );
			if ( null !== $expected_revision && ( ! is_array( $current ) || (int) ( $current['revision'] ?? 0 ) !== $expected_revision ) ) {
				return false;
			}
			if ( is_array( $current ) && empty( $current['state']['design']['baseRevision'] ) && ! self::retain_recovery( $current ) ) {
				return false;
			}
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
			! isset( $design['name'], $design['flatRate'], $design['active'] )
			|| ! is_string( $design['name'] )
			// Older editors include this metadata; current editors no longer expose it.
			|| ( array_key_exists( 'customType', $design ) && ! in_array( $design['customType'], [ 'text_only', 'photo_text' ], true ) )
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
