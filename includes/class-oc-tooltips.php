<?php
/**
 * Contextual help disclosures — renders a consistent ? icon + click hint.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Tooltips {

	/**
	 * Render a keyboard-accessible help disclosure.
	 *
	 * @param string $id   Identifier prefix; each rendered hint gets a unique ID.
	 * @param string $text Translated plain-text help message.
	 * @return void
	 */
	public static function render( string $id, string $text ): void {
		$hint_id = wp_unique_id( 'oc-help-' . sanitize_html_class( $id ) . '-' );
		?>
		<span class="oc-help-tooltip" data-oc-tooltip="<?php echo esc_attr( $id ); ?>" data-oc-help-disclosure>
			<button type="button" class="oc-help-toggle"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $hint_id ); ?>"
				aria-label="<?php esc_attr_e( 'Help', 'overcustomise' ); ?>">
				?
			</button>
			<span class="oc-help-hint" id="<?php echo esc_attr( $hint_id ); ?>" hidden><?php echo esc_html( $text ); ?></span>
		</span>
		<?php
	}
}
