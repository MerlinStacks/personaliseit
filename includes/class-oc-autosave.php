<?php

defined( 'ABSPATH' ) || exit;

class OC_Autosave {

	private const TRANSIENT_PREFIX = 'oc_autosave_';
	private const TTL_SECONDS      = 300;

	public static function store( int $design_id, array $state ): bool {
		$key = self::TRANSIENT_PREFIX . $design_id;
		$data = [
			'state'     => $state,
			'timestamp' => time(),
		];
		return set_transient( $key, $data, self::TTL_SECONDS ) !== false;
	}

	public static function restore( int $design_id ): ?array {
		$key = self::TRANSIENT_PREFIX . $design_id;
		$data = get_transient( $key );
		if ( false === $data || ! is_array( $data ) || ! isset( $data['state'] ) ) {
			return null;
		}
		return [
			'state'     => $data['state'],
			'timestamp' => (int) ( $data['timestamp'] ?? 0 ),
		];
	}

	public static function clear( int $design_id ): bool {
		$key = self::TRANSIENT_PREFIX . $design_id;
		return delete_transient( $key );
	}
}
