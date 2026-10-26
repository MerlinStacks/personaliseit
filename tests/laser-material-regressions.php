<?php
/** Dependency-free checks: php tests/laser-material-regressions.php */
require __DIR__ . '/bootstrap.php';
require_once OC_PATH . 'includes/class-oc-svg-sanitiser.php';
require_once OC_PATH . 'includes/class-oc-cut-line.php';
require_once OC_PATH . 'includes/class-oc-render-math.php';
require_once OC_PATH . 'includes/class-oc-render-spec.php';

class OC_DB {
	public static array $areas  = [];
	public static array $layers = [];
	public static function get_design_print_areas( int $design_id ): array {
		return array_filter( self::$areas, static fn ( $area ) => $area->design_id === $design_id );
	}
	public static function get_design_layers( int $design_id ): array {
		return array_filter( self::$layers, static fn ( $layer ) => $layer->design_id === $design_id );
	}
}

function material_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$area           = (object) [
	'id'                 => 12,
	'design_id'          => 4,
	'area_key'           => 'front',
	'label'              => 'Front',
	'print_method'       => 'engraving',
	'engraving_material' => 'leather',
	'visible'            => true,
];
$area->canvas_x = 0;
$area->canvas_y = 0;
$area->canvas_w = 400;
$area->canvas_h = 300;
OC_DB::$areas   = [ $area ];
material_check( count( OC_Materials::all() ) === 6, 'Six starter materials' );
material_check( OC_Materials::normalise_selection( 4, [] ) === [], 'Legacy engraving needs no material layer' );
material_check( 'leather' === $area->engraving_material, 'Existing leather finish untouched' );
$settings      = [
	'materialSvg'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill-rule="evenodd" d="M0 0H100V100H0Z M25 25V75H75V25Z"/></svg>',
	'material_ids'        => [ 'black-white', 'pink-black' ],
	'default_material_id' => 'pink-black',
];
$layer         = (object) [
	'id'        => 37,
	'area_id'   => 12,
	'design_id' => 4,
	'type'      => 'laser_material',
	'label'     => 'Plaque material',
	'visible'   => true,
	'locked'    => false,
	'settings'  => $settings,
];
OC_DB::$layers = [ $layer ];
$config        = OC_Materials::for_layer( $layer );
material_check( 'pink-black' === $config['defaultId'], 'Layer default retained' );
material_check( count( $config['materials'] ) === 2, 'Only assigned layer materials offered' );
update_option(
	'oc_laser_material_groups',
	[
		'acrylics' => [
			'id'           => 'acrylics',
			'name'         => 'Acrylics',
			'material_ids' => [ 'black-white', 'white-black' ],
		],
	]
);
$grouped_settings = OC_Materials::layer_settings(
	$settings + [ 'material_group_ids' => [ 'acrylics' ] ]
);
material_check( [ 'black-white', 'white-black' ] === $grouped_settings['material_ids'], 'Material groups resolve their member materials' );
$layer->settings = $grouped_settings;
material_check( count( OC_Materials::for_layer( $layer )['materials'] ) === 2, 'Grouped layer offers the group members' );
$layer->settings = $settings;
material_check( is_wp_error( OC_Materials::normalise_selection( 4, [] ) ), 'Missing layer selection rejected' );
material_check( is_wp_error( OC_Materials::normalise_selection( 4, [ 37 => 'white-black' ] ) ), 'Unassigned material rejected' );
material_check( is_wp_error( OC_Materials::normalise_selection( 4, [ 37 => [ 'id' => 'pink-black' ] ] ) ), 'Non-scalar choice rejected' );
material_check(
	is_wp_error(
		OC_Materials::normalise_selection(
			4,
			[
				37 => 'pink-black',
				99 => 'black-white',
			]
		)
	),
	'Unknown layer rejected'
);
$snapshot = OC_Materials::normalise_selection( 4, [ 37 => 'pink-black' ] );
material_check( 'Pink with Black Engraving' === $snapshot[37]['name'], 'Authoritative name snapshotted' );
material_check( 'Plaque material' === $snapshot[37]['areaLabel'], 'Material layer label snapshotted' );
$production = OC_Render_Spec::build( 4, [] );
material_check( [] === $production['areas'][12]['layers'], 'SVG surface excluded from production render spec' );
material_check( 'leather' === $production['areas'][12]['engravingMaterial'], 'Production finish remains independent of material layer' );
material_check( str_contains( OC_Materials::layer_settings( $settings )['materialSvg'], 'evenodd' ), 'SVG holes retained' );
$layer->locked = true;
material_check( ! is_wp_error( OC_Materials::normalise_selection( 4, [] ) ), 'Locked layer uses default' );
material_check( is_wp_error( OC_Materials::normalise_selection( 4, [ 37 => 'black-white' ] ) ), 'Locked default cannot be changed' );
$layer->locked                   = false;
$library                         = OC_Materials::all();
$library['pink-black']['active'] = false;
update_option( 'oc_laser_materials', $library );
material_check( count( OC_Materials::for_layer( $layer )['materials'] ) === 1, 'Inactive material hidden' );
material_check( 'black-white' === OC_Materials::for_layer( $layer )['defaultId'], 'Inactive default falls back' );
material_check( is_wp_error( OC_Materials::normalise_selection( 4, [ 37 => 'pink-black' ] ) ), 'Inactive material rejected' );
material_check( 'Pink with Black Engraving' === $snapshot[37]['name'], 'Snapshot unaffected by edits' );
$layer->visible = false;
material_check( OC_Materials::normalise_selection( 4, [] ) === [], 'Hidden layer has no selection requirement' );
$layer->visible     = true;
$area->print_method = 'uv';
material_check( OC_Materials::normalise_selection( 4, [] ) === [], 'Material layer cannot affect non-engraving areas' );
$safe = OC_Materials::sanitise(
	[
		'id'        => [],
		'name'      => [],
		'surface'   => 'url(evil)',
		'engraving' => [],
	]
);
material_check( '' === $safe['id'] && '' === $safe['name'] && '#171717' === $safe['surface'], 'Malformed fields are safe' );
echo "Laser material layer regressions passed.\n";
