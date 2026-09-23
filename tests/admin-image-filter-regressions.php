<?php
/** Standalone save checks: php tests/admin-image-filter-regressions.php */
define( 'ABSPATH', __DIR__ . '/../' );
function __( $value, ...$args ) {
	return $value;
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}
// Minimal sanitizers for these fixtures; WordPress itself is not under test.
function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Minimal standalone sanitizer stub; WordPress is not loaded.
}
function sanitize_textarea_field( $value ) {
	return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Minimal standalone sanitizer stub; WordPress is not loaded.
}
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion diagnostics are plain text, not HTML.
	}
}
class OC_DB {
	public static $filters      = [];
	public static $cache_clears = 0;
	public static function get_image_filters( $active_only = true ) {
		check( false === $active_only, 'Editing must also find inactive filters' );
		return self::$filters;
	}
	public static function clear_image_filter_cache() {
		++self::$cache_clears;
	}
}
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone database double for the production method's global dependency; WordPress is not loaded.
$wpdb = new class() {
	public $prefix = 'regression_';
	public $writes = [];
	public function insert( $table, $data, $formats ) {
		$this->writes[] = [
			'operation' => 'insert',
			'table'     => $table,
			'data'      => $data,
			'formats'   => $formats,
		];
		return 1;
	}
	public function update( $table, $data, $where, $formats, $where_formats ) {
		$this->writes[] = [
			'operation'     => 'update',
			'table'         => $table,
			'data'          => $data,
			'formats'       => $formats,
			'where'         => $where,
			'where_formats' => $where_formats,
		];
		return 0; // An unchanged row is still a successful save.
	}
};
require __DIR__ . '/../includes/admin/class-oc-admin-image-filters.php';
$save  = new ReflectionMethod( OC_Admin_Image_Filters::class, 'handle_save' );
$admin = new OC_Admin_Image_Filters();
$cases = 0;

function save_case( array $post, bool $expected, string $label ): ?array {
	global $wpdb, $save, $admin, $cases;
	$_POST               = $post;
	$wpdb->writes        = [];
	OC_DB::$cache_clears = 0;
	check( $expected === $save->invoke( $admin ), $label . ': save result' );
	check( ( $expected ? 1 : 0 ) === count( $wpdb->writes ), $label . ': write count' );
	check( ( $expected ? 1 : 0 ) === OC_DB::$cache_clears, $label . ': cache invalidation count' );
	++$cases;
	if ( ! $expected ) {
		return null;
	}
	$write = $wpdb->writes[0];
	check( 'regression_oc_image_filters' === $write['table'], $label . ': prefixed table' );
	check( [ '%s', '%s', '%f', '%s', '%d', '%d' ] === $write['formats'], $label . ': persistence formats' );
	check( 1 === $write['data']['active'], $label . ': saved filter is active' );
	check( ( empty( $post['filter_id'] ) ? 'insert' : 'update' ) === $write['operation'], $label . ': persistence operation' );
	return $write;
}

foreach ( [ 'grayscale', 'sepia', 'negative' ] as $key ) {
	foreach ( [ [], [ 'prompt' => '' ] ] as $prompt_fields ) {
		$write = save_case(
			array_merge(
				[
					'name'       => 'Standard',
					'filter_key' => $key,
				],
				$prompt_fields
			),
			true,
			$key . ' without prompt'
		);
		check( $key === $write['data']['filter_key'] && 1 === $write['data']['value'], $key . ': fixed effect type and value' );
		check( '' === $write['data']['prompt'] && 0 === $write['data']['remove_background'], $key . ': no AI settings' );
	}
}

foreach ( [ 'brightness', 'contrast', 'saturation', 'hue' ] as $key ) {
	foreach ( [ '-1', '-0.35', '0', '0.25', '1' ] as $value ) {
		$write = save_case(
			[
				'name'       => 'Adjustable',
				'filter_key' => $key,
				'value'      => $value,
			],
			true,
			$key . ' amount ' . $value
		);
		check( $key === $write['data']['filter_key'] && (float) $value === $write['data']['value'], $key . ': preserve numeric amount' );
	}
	foreach ( [ null, '', ' ', 'invalid', '-1.01', '1.01', '1e309', '-1e309', 'NaN', 'INF' ] as $value ) {
		$fixture_post = [
			'name'       => 'Adjustable',
			'filter_key' => $key,
		];
		if ( null !== $value ) {
			$fixture_post['value'] = $value;
		}
		save_case( $fixture_post, false, $key . ' rejects amount ' . var_export( $value, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Include the invalid fixture value in CLI assertion diagnostics.
	}
}

foreach ( [ null, '', " \n\t ", str_repeat( 'x', 10001 ) ] as $prompt ) {
	$fixture_post = [
		'name'       => 'AI',
		'filter_key' => 'ai',
	];
	if ( null !== $prompt ) {
		$fixture_post['prompt'] = $prompt;
	}
	save_case( $fixture_post, false, 'AI rejects missing, blank or oversized prompt (' . ( null === $prompt ? 'missing' : strlen( $prompt ) ) . ')' );
}
$prompt = "Keep the pet's markings.\nUse \"ink\" outlines and C:\\art as a reference.";
foreach ( [ 0, 1 ] as $remove_background ) {
	$write = save_case(
		[
			'name'              => addslashes( "Pet's outline" ),
			'filter_key'        => 'ai',
			'prompt'            => addslashes( $prompt ),
			'remove_background' => (string) $remove_background,
		],
		true,
		'AI prompt with background flag ' . $remove_background
	);
	check(
		[
			'name'              => "Pet's outline",
			'filter_key'        => 'ai',
			'value'             => 1,
			'prompt'            => $prompt,
			'remove_background' => $remove_background,
			'active'            => 1,
		] === $write['data'],
		'AI persists unslashed multiline prompt and background preference'
	);
}
$write = save_case(
	[
		'name'       => 'AI boundary',
		'filter_key' => 'ai',
		'prompt'     => str_repeat( 'x', 10000 ),
	],
	true,
	'AI maximum prompt length'
);
check( str_repeat( 'x', 10000 ) === $write['data']['prompt'], 'Maximum-length AI prompt is saved intact' );

foreach ( [ '', 'unknown', 'invert', 'ai_invalid', '../' ] as $key ) {
	save_case(
		[
			'name'       => 'Invalid type',
			'filter_key' => $key,
			'prompt'     => 'Valid prompt',
			'value'      => '0.5',
		],
		false,
		'Reject invalid key ' . var_export( $key, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Include the invalid fixture key in CLI assertion diagnostics.
	);
}

// Submit an existing standard filter as the edit form does, including its type/value.
foreach ( [ 'grayscale', 'sepia', 'negative', 'brightness', 'contrast', 'saturation', 'hue' ] as $key ) {
	$value          = in_array( $key, [ 'grayscale', 'sepia', 'negative' ], true ) ? 1 : -0.4;
	OC_DB::$filters = [
		(object) [
			'id'         => 42,
			'name'       => 'Existing',
			'filter_key' => $key,
			'value'      => $value,
			'active'     => 0,
		],
	];
	$write          = save_case(
		[
			'filter_id'  => '42',
			'name'       => 'Renamed',
			'filter_key' => $key,
			'value'      => (string) $value,
		],
		true,
		'Edit ' . $key
	);
	check( [ 'id' => 42 ] === $write['where'] && [ '%d' ] === $write['where_formats'], $key . ': update only requested ID' );
	check( 'Renamed' === $write['data']['name'] && $key === $write['data']['filter_key'] && $value === $write['data']['value'], $key . ': editing retains standard type/value' );

	// Converting an AI filter must explicitly overwrite stale AI-only columns.
	OC_DB::$filters = [
		(object) [
			'id'                => 42,
			'filter_key'        => 'ai',
			'prompt'            => 'Old prompt',
			'remove_background' => 1,
		],
	];
	$write          = save_case(
		[
			'filter_id'         => '42',
			'name'              => 'Converted',
			'filter_key'        => $key,
			'value'             => (string) $value,
			'prompt'            => 'Stale submitted AI prompt',
			'remove_background' => '1',
		],
		true,
		'Clear AI settings for ' . $key
	);
	check( '' === $write['data']['prompt'] && 0 === $write['data']['remove_background'], $key . ': explicitly clear prompt/background on update' );
	check( $key === $write['data']['filter_key'] && $value === $write['data']['value'], $key . ': conversion saves selected effect' );
}
OC_DB::$filters = [];
save_case(
	[
		'filter_id'  => '999',
		'name'       => 'Missing',
		'filter_key' => 'negative',
	],
	false,
	'Reject nonexistent edit ID'
);
print "Admin image filter regression checks passed ({$cases} save cases).\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text CLI test summary.
