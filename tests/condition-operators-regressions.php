<?php
/** Condition operator/action parity using the existing standalone WordPress doubles. */
require __DIR__ . '/layer-cost-regressions.php';

$source = layer( 1, 'clipart', [ 'condition_key' => 'art', 'default_clipart_id' => 10 ] );
foreach ( [ 'is', 'is_not' ] as $operator ) {
	foreach ( [ 'show', 'hide' ] as $action ) {
		$target = layer( 2, 'staff_note', [
			'condition_enabled' => true,
			'condition_source_key' => 'art',
			'condition_clipart_ids' => [ 10, 12 ],
			'condition_operator' => $operator,
			'condition_action' => $action,
		] );
		foreach ( [ 10, 12, 11, 0 ] as $id ) {
			$matches = in_array( $id, [ 10, 12 ], true );
			$applies = 'is_not' === $operator ? ! $matches : $matches;
			$expected = 'hide' === $action ? ! $applies : $applies;
			check( $expected === OC_Cart::conditional_layer_is_shown( $target, [ $source, $target ], [ 1 => [ 'clipartId' => $id ] ] ), "Rule parity: $operator / $action / $id" );
		}
		check( ! OC_Cart::conditional_layer_is_shown( $target, [ $target ], [] ), 'Missing sources stay hidden for all operators/actions' );
		$target->settings['condition_clipart_ids'] = [];
		check( ! OC_Cart::conditional_layer_is_shown( $target, [ $source, $target ], [] ), 'Incomplete rules stay hidden' );
	}
}
$settings = OC_Cart::normalise_layer_settings( [] );
check( 'is' === $settings['condition_operator'] && 'show' === $settings['condition_action'], 'Existing rules retain show/is defaults' );
$settings = OC_Cart::normalise_layer_settings( [ 'condition_operator' => [ 'bad' ], 'condition_action' => 'bad' ] );
check( 'is' === $settings['condition_operator'] && 'show' === $settings['condition_action'], 'Unsupported operators/actions normalize safely' );
echo "Condition operator regressions passed.\n";
