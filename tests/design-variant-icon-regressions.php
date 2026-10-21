<?php
/** Verify chooser icons bypass thumbnail generation, not design validation. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$options = [ 'oc_chooser_icon_design_7' => 88 ];
function get_option( $key, $fallback = false ) {
	return $GLOBALS['options'][ $key ] ?? $fallback;
}
function wp_get_attachment_image_url( $id, $size ) {
	return 88 === $id ? 'https://example.test/icon.png' : false;
}
function sanitize_text_field( $value ) {
	return strip_tags( $value );
}
function __( $value, ...$args ) {
	return $value;
}
class OC_DB {
	public static int $thumbnail_reads = 0;
	public static function get_design_layers( $id ) {
		++self::$thumbnail_reads;
		return [];
	}
}
require ABSPATH . 'includes/frontend/class-oc-frontend.php';
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
$frontend = ( new ReflectionClass( OC_Frontend::class ) )->newInstanceWithoutConstructor();
$cache = new ReflectionProperty( OC_Frontend::class, 'design_context_cache' );
$cache->setValue( $frontend, [
	7 => [
		'design' => (object) [ 'name' => 'Design seven' ],
		'areas'  => [ (object) [ 'id' => 1 ] ],
		'layers' => [],
	],
	8 => null,
] );
$build = new ReflectionMethod( OC_Frontend::class, 'build_design_variant_option' );
$option = $build->invoke( $frontend, 7, 'Artwork seven', 7 );
check( 'https://example.test/icon.png' === $option['iconUrl'], 'The uploaded icon is the chooser image' );
check( [] === $option['thumbLayers'] && '' === $option['thumbUrl'] && 0 === OC_DB::$thumbnail_reads, 'An icon skips all generated-thumbnail work' );
check( true === $option['selected'] && 7 === $option['designId'] && 'Artwork seven' === $option['label'], 'The design remains selectable with its original identity' );
$options['oc_chooser_icon_design_8'] = 88;
check( null === $build->invoke( $frontend, 8, '', 7 ), 'An icon cannot make an unusable design selectable' );
foreach ( [ 0, 999 ] as $attachment_id ) {
	$options['oc_chooser_icon_design_7'] = $attachment_id;
	OC_DB::$thumbnail_reads = 0;
	$option = $build->invoke( $frontend, 7, '', 7 );
	check( '' === $option['iconUrl'] && 2 === OC_DB::$thumbnail_reads, 'Removed or missing icons restore the existing thumbnail generation path' );
}
echo "Design variant icon regressions passed.\n";
