<?php
/**
 * Contextual help tooltips — renders a consistent ? icon + hover/click hint.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Tooltips {

	public static function render( string $id, string $text ): void {
		$escaped_id  = esc_attr( $id );
		$escaped_text = esc_html( $text );
		?>
		<span class="oc-help-tooltip" data-oc-tooltip="<?php echo $escaped_id; ?>">
			<button type="button" class="oc-help-toggle"
				aria-expanded="false"
				aria-haspopup="true"
				aria-label="<?php echo $escaped_text; ?>">
				?
			</button>
			<span class="oc-help-hint"><?php echo $escaped_text; ?></span>
		</span>
		<?php
	}
}
