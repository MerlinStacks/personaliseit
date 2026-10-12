<?php
// Render trusted local template fragments in a standalone CLI fixture.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, Squiz.PHP.Eval.Discouraged
/** Render the real filter-controls template for fixed and customer-selectable effects. */
function esc_attr( mixed $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( mixed $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html_e( string $value, string $domain ): void {
	echo esc_html( $value ); }
function selected( mixed $value, mixed $expected ): void {
	if ( $value === $expected ) {
		echo 'selected'; } }

$template = file_get_contents( __DIR__ . '/../templates/frontend/customiser-panel.php' );
$start    = strpos( $template, '<?php if ( in_array( $layer->type, [ \'image\', \'ai_image\' ], true ) && ! empty( $image_filter_ids ) && $allow_image_filter_change )' );
$end      = strpos( $template, '<?php if ( in_array( $layer->type, [ \'image\', \'ai_image\' ], true ) && $enable_image_colour' );
if ( false === $start || false === $end ) {
	throw new RuntimeException( 'Filter controls template could not be located.' ); }
$controls                = substr( $template, $start, $end - $start );
$layer                   = (object) [
	'id'   => 12,
	'type' => 'image',
];
$image_filter_ids        = [ 7 ];
$default_image_filter_id = 7;
$allow_image_change      = true;
$all_image_filters       = [
	(object) [
		'id'         => 7,
		'filter_key' => 'ai',
		'name'       => 'Line art',
	],
];
foreach ( [ false, true ] as $allow_image_filter_change ) {
	ob_start();
	eval( '?>' . $controls );
	$html = ob_get_clean();
	if ( 1 !== substr_count( $html, 'data-oc-image-filter-results=' ) || ! str_contains( $html, 'data-oc-image-filter-retry=' ) ) {
		throw new RuntimeException( 'Result choices and retry controls must exist for fixed and selectable AI effects.' );
	}
	if ( str_contains( $html, '<select' ) !== $allow_image_filter_change ) {
		throw new RuntimeException( 'Fixed effects must not expose the filter selector.' );
	}
	$expected = $allow_image_filter_change ? 'true' : 'false';
	if ( ! str_contains( $html, 'data-oc-filter-allow-original="' . $expected . '"' ) ) {
		throw new RuntimeException( 'Original-image availability must match the filter policy.' );
	}
	if ( ! str_contains( $html, 'class="oc-image-filter-quota"' ) ) {
		throw new RuntimeException( 'The remaining allowance must appear below the retry panel.' );
	}
}
$all_image_filters[0]->filter_key = 'grayscale';
ob_start();
eval( '?>' . $controls );
$html = ob_get_clean();
if ( str_contains( $html, 'data-oc-image-filter-results=' ) ) {
	throw new RuntimeException( 'Ordinary filters should not expose AI retry controls.' );
}
echo "AI filter controls regressions passed.\n";
