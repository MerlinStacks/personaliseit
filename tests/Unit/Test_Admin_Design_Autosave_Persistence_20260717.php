<?php
/**
 * Focused tests for admin design autosave persistence.
 *
 * @package OverCustomise
 */

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value );
	}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ): mixed {
		if ( is_callable( $GLOBALS['oc_autosave_test_on_read'] ?? null ) ) {
			$callback = $GLOBALS['oc_autosave_test_on_read'];
			$GLOBALS['oc_autosave_test_on_read'] = null;
			$callback();
		}
		return $GLOBALS['oc_autosave_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, mixed $value, int $expiration ): bool {
		$GLOBALS['oc_autosave_test_transients'][ $key ] = $value;
		$GLOBALS['oc_autosave_test_expirations'][ $key ] = $expiration;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ): bool {
		unset( $GLOBALS['oc_autosave_test_transients'][ $key ] );
		return true;
	}
}

require_once OC_PATH . 'includes/class-oc-autosave.php';

class Test_Admin_Design_Autosave_Persistence_20260717 extends PHPUnit\Framework\TestCase {
	private mixed $previous_wpdb;

	protected function setUp(): void {
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb'] = new class {
			public array $locks = [];
			public int $lock_attempts = 0;

			public function prepare( string $query, mixed ...$args ): string {
				return $query . ' /*' . (string) ( $args[0] ?? '' ) . '*/';
			}

			public function get_var( string $query ): int {
				preg_match( '#/\*(.*)\*/$#', $query, $matches );
				$name = (string) ( $matches[1] ?? '' );
				if ( str_contains( $query, 'GET_LOCK' ) ) {
					$this->lock_attempts++;
					if ( isset( $this->locks[ $name ] ) ) {
						return 0;
					}
					$this->locks[ $name ] = true;
					return 1;
				}
				if ( str_contains( $query, 'RELEASE_LOCK' ) ) {
					unset( $this->locks[ $name ] );
					return 1;
				}
				return 0;
			}
		};
		$GLOBALS['oc_autosave_test_transients']  = [];
		$GLOBALS['oc_autosave_test_expirations'] = [];
		$GLOBALS['oc_autosave_test_on_read']     = null;
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;
		$GLOBALS['oc_autosave_test_on_read'] = null;
		parent::tearDown();
	}

	public function test_complete_design_state_requires_canvas_unit(): void {
		$method = new ReflectionMethod( OC_Autosave::class, 'is_valid_state' );
		$state  = $this->complete_state();

		$this->assertTrue( $method->invoke( null, $state ) );

		unset( $state['areas'][0]['unit'] );
		$this->assertFalse( $method->invoke( null, $state ) );
	}

	public function test_state_without_design_properties_is_rejected(): void {
		$method = new ReflectionMethod( OC_Autosave::class, 'is_valid_state' );
		$state  = $this->complete_state();

		unset( $state['design']['flatRate'] );

		$this->assertFalse( $method->invoke( null, $state ) );
	}

	public function test_stale_revision_cannot_replace_newer_one_and_ttl_is_one_day(): void {
		$method = new ReflectionMethod( OC_Autosave::class, 'store_for_key' );
		$key    = 'oc_autosave_test_revision';
		$first  = $this->complete_state();
		$newer  = $first;
		$newer['design']['name'] = 'Newer name';

		$stored = $method->invoke( null, $key, $first, 1, 0 );
		$stale  = $method->invoke( null, $key, $newer, 1, 0 );

		$this->assertSame( 'stored', $stored['status'] );
		$this->assertSame( 86400, $GLOBALS['oc_autosave_test_expirations'][ $key ] );
		$this->assertSame( 'conflict', $stale['status'] );
		$this->assertSame( 1, $stale['revision'] );
		$this->assertSame( 'Autosaved design', $GLOBALS['oc_autosave_test_transients'][ $key ]['state']['design']['name'] );

		$next = $method->invoke( null, $key, $newer, 2, 1 );
		$this->assertSame( 'stored', $next['status'] );
		$this->assertSame( 'Newer name', $GLOBALS['oc_autosave_test_transients'][ $key ]['state']['design']['name'] );
	}

	public function test_concurrent_compare_and_set_cannot_enter_the_same_revision_lock(): void {
		$method = new ReflectionMethod( OC_Autosave::class, 'store_for_key' );
		$key    = 'oc_autosave_test_concurrent';
		$state  = $this->complete_state();
		$nested = null;
		$GLOBALS['oc_autosave_test_on_read'] = static function () use ( $method, $key, $state, &$nested ): void {
			$nested = $method->invoke( null, $key, $state, 1, 0 );
		};

		$outer = $method->invoke( null, $key, $state, 1, 0 );

		$this->assertSame( 'stored', $outer['status'] );
		$this->assertSame( 'failed', $nested['status'] );
		$this->assertSame( 2, $GLOBALS['wpdb']->lock_attempts );
		$this->assertSame( 1, $GLOBALS['oc_autosave_test_transients'][ $key ]['revision'] );
	}

	private function complete_state(): array {
		return [
			'design' => [
				'name'       => 'Autosaved design',
				'customType' => 'photo_text',
				'flatRate'   => 12.5,
				'active'     => true,
			],
			'areas'  => [
				[
					'id'          => 11,
					'label'       => 'Front',
					'method'      => 'uv',
					'material'    => 'silver_metal',
					'unit'        => 'mm',
					'mockupId'    => 42,
					'mockupUrl'   => 'https://example.com/mockup.jpg',
					'x'           => 1,
					'y'           => 2,
					'w'           => 100,
					'h'           => 50,
					'dpi'         => 300,
					'ratioLocked' => true,
					'aspectRatio' => 2,
					'rotation'    => 0,
					'sortOrder'   => 0,
					'visible'     => true,
					'locked'      => false,
					'layers'      => [
						[
							'id'        => 21,
							'type'      => 'text',
							'label'     => 'Name',
							'x'         => 0,
							'y'         => 0,
							'w'         => 100,
							'h'         => 20,
							'sortOrder' => 0,
							'visible'   => true,
							'locked'    => false,
							'settings'  => [ 'default_text' => 'Example' ],
						],
					],
				],
			],
		];
	}
}
