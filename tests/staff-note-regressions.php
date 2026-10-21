<?php
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated CLI fixture, not WordPress request globals.
/** Standalone staff-note cart regressions using the existing WordPress boundary doubles. */
require __DIR__ . '/layer-cost-regressions.php';

OC_DB::$layers = [
	layer(
		1,
		'staff_note',
		[
			'required'   => true,
			'char_limit' => 50,
		]
	),
];
check( is_wp_error( normalise( [ 1 => [ 'value' => '   ' ] ] ) ), 'Visible required notes block empty submission' );
$note   = "Please check the spelling.\nUse <name> literally.";
$result = normalise(
	[
		1 => [
			'value'        => $note,
			'label'        => 'Forged label',
			'attachmentId' => 20,
			'fontId'       => 123,
		],
	]
);
check( ! is_wp_error( $result ), 'Notes do not require fonts or production attachments' );
check(
	[
		'type'  => 'staff_note',
		'label' => 'Layer 1',
		'value' => $note,
	] === $result['layers'][1],
	'Store only the authoritative label and literal multiline note'
);
check( [] === $result['layer_costs'], 'Staff notes are never charged as printable text' );
check( is_wp_error( normalise( [ 1 => [ 'value' => str_repeat( 'x', 51 ) ] ] ) ), 'Note character limits are enforced on the server' );
$map = OC_Cart::render_spec_layer_map(
	[
		'renderSpec' => [ 'areas' => [] ],
		'layers'     => $result['layers'],
	]
);
check( 'Layer 1' === $map[1]->label, 'Staff note labels survive independently of print specifications' );
$cart    = ( new ReflectionClass( OC_Cart::class ) )->newInstanceWithoutConstructor();
$display = new ReflectionMethod( OC_Cart::class, 'layer_display_value' );
$html    = $display->invoke( $cart, $result['layers'][1], $map[1], true );
check( str_contains( $html, '&lt;name&gt;' ) && str_contains( $html, '<br' ), 'Staff order summaries escape note text and preserve line breaks' );
OC_DB::$layers[0]->settings['condition_enabled']     = true;
OC_DB::$layers[0]->settings['condition_source_key']  = 'missing';
OC_DB::$layers[0]->settings['condition_clipart_ids'] = [ 10 ];
OC_DB::$layers[]                                     = layer( 2, 'text' );
$result = normalise( [ 1 => [ 'value' => '' ] ] );
check( ! is_wp_error( $result ) && ! isset( $result['layers'][1] ), 'Hidden required staff notes are omitted without blocking checkout' );
echo "Staff-note regressions passed.\n";
