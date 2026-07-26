<?php
/**
 * Frontend customiser panel — vanilla JS edition.
 * Variables: $design, $areas, $layers
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $areas ) ) return;

$areas = array_values( array_filter( $areas, static fn ( $area ): bool => ! isset( $area->visible ) || (bool) $area->visible ) );
$visible_area_ids = array_fill_keys( array_filter( array_map( static fn ( $area ): int => absint( $area->id ?? 0 ), $areas ) ), true );
$layers = array_values( array_filter(
	$layers,
	static fn ( $layer ): bool => ( ! isset( $layer->visible ) || (bool) $layer->visible ) && ! empty( $visible_area_ids[ absint( $layer->area_id ?? 0 ) ] )
) );
if ( empty( $areas ) || empty( $layers ) ) return;

$layers_by_area = [];
foreach ( $layers as $layer ) {
	$layers_by_area[ (int) $layer->area_id ][] = $layer;
}

$all_fonts          = OC_Plugin::browser_fonts();
$fonts_by_id        = [];
foreach ( $all_fonts as $font ) {
	$fonts_by_id[ (int) $font['id'] ] = $font;
}
$all_image_filters  = OC_DB::get_image_filters( true );
$active_image_filter_ids = array_map( static fn ( $filter ): int => (int) $filter->id, $all_image_filters );
$valid_font_group_ids = array_map( static fn ( $group ): int => (int) $group->id, OC_DB::get_font_groups() );
$has_spotify_layer  = false;
$colour_modal_threshold = 6;

$colour_contrast_text = static function ( string $hex ): string {
	$hex = ltrim( sanitize_hex_color( $hex ) ?: '#000000', '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	$luminance = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;

	return $luminance >= 150 ? '#1f2933' : '#ffffff';
};

$render_colour_picker = static function ( array $colours, int $layer_id, string $default_colour, string $context ) use ( $colour_contrast_text, $colour_modal_threshold ): void {
	$use_modal      = count( $colours ) > $colour_modal_threshold;
	$selected       = $colours[0];
	$selected_index = 0;

	foreach ( $colours as $index => $colour ) {
		if ( strtolower( (string) $colour->hex ) === strtolower( $default_colour ) ) {
			$selected       = $colour;
			$selected_index = $index;
			break;
		}
	}

	$dialog_id = 'oc-colour-dialog-' . $layer_id;
	if ( $use_modal ) :
		?>
		<div class="oc-colour-picker">
			<button type="button" class="oc-colour-picker-trigger" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $dialog_id ); ?>" data-oc-colour-dialog-trigger="<?php echo esc_attr( $dialog_id ); ?>">
				<span class="oc-colour-picker-preview" style="background:<?php echo esc_attr( $selected->hex ); ?>;" data-oc-colour-picker-preview aria-hidden="true"></span>
				<span data-oc-colour-picker-label><?php echo esc_html( $selected->name ); ?></span>
				<span class="oc-colour-picker-action"><?php esc_html_e( 'Change', 'overcustomise' ); ?></span>
			</button>
			<dialog id="<?php echo esc_attr( $dialog_id ); ?>" class="oc-colour-dialog" aria-labelledby="<?php echo esc_attr( $dialog_id ); ?>-title" data-oc-colour-dialog>
				<div class="oc-colour-dialog-card">
					<div class="oc-colour-dialog-header">
						<h2 id="<?php echo esc_attr( $dialog_id ); ?>-title"><?php esc_html_e( 'Choose a colour', 'overcustomise' ); ?></h2>
						<button type="button" class="oc-colour-dialog-close" data-oc-colour-dialog-close aria-label="<?php esc_attr_e( 'Close colour picker', 'overcustomise' ); ?>">&times;</button>
					</div>
	<?php endif; ?>
	<div class="oc-colour-swatches<?php echo $use_modal ? ' oc-colour-swatches--modal' : ''; ?>">
		<?php foreach ( $colours as $index => $colour ) :
			$is_selected = $index === $selected_index;
			if ( 'text' === $context ) {
				$aria_label = sprintf( __( 'Select %s text colour', 'overcustomise' ), $colour->name );
			} elseif ( 'clipart' === $context ) {
				$aria_label = sprintf( __( 'Select %s clipart colour', 'overcustomise' ), $colour->name );
			} else {
				$aria_label = sprintf( __( 'Select %s', 'overcustomise' ), $colour->name );
			}
			?>
			<button type="button" class="oc-colour-swatch<?php echo $is_selected ? ' oc-selected' : ''; ?>"
				style="background:<?php echo esc_attr( $colour->hex ); ?>;color:<?php echo esc_attr( $colour_contrast_text( (string) $colour->hex ) ); ?>;"
				aria-label="<?php echo esc_attr( $aria_label ); ?>"
				aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
				data-oc-layer-swatch="<?php echo esc_attr( $layer_id ); ?>"
				data-colour-name="<?php echo esc_attr( $colour->name ); ?>"
				data-hex="<?php echo esc_attr( $colour->hex ); ?>">
				<span><?php echo esc_html( $colour->name ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>
	<?php if ( $use_modal ) : ?>
				</div>
			</dialog>
		</div>
		<?php
	endif;
};

foreach ( $layers as $layer ) {
	if ( 'spotify' === (string) $layer->type && (bool) $layer->visible && empty( $layer->locked ) ) {
		$has_spotify_layer = true;
		break;
	}
}
?>

<div id="oc-customiser-panel" class="oc-customiser-panel<?php echo esc_attr( ! empty( $design_variants ) ? ' oc-has-design-variants' : '' ); ?>">

	<div id="oc-preflight-messages" class="oc-preflight-messages" hidden></div>

	<?php if ( ! empty( $design_variants ) ) : ?>
		<?php $oc_thumb_protocols = array_merge( wp_allowed_protocols(), [ 'data' ] ); ?>
		<div class="oc-design-variants" aria-label="<?php esc_attr_e( 'Artwork options', 'overcustomise' ); ?>">
			<div class="oc-control-group">
				<label><?php esc_html_e( 'Artwork Option', 'overcustomise' ); ?></label>
				<div class="oc-design-variant-carousel" data-oc-design-variant-carousel>
					<button type="button" class="oc-design-variant-carousel-arrow oc-design-variant-carousel-arrow--prev" data-oc-design-variant-prev aria-label="<?php esc_attr_e( 'Previous artwork options', 'overcustomise' ); ?>">‹</button>
					<div class="oc-design-variant-grid" data-oc-design-variant-track>
					<?php $has_selected_design_variant = ! empty( array_filter( $design_variants, fn( $item ) => ! empty( $item['selected'] ) ) ); ?>
					<?php foreach ( $design_variants as $i => $variant ) :
						$is_selected = ! empty( $variant['selected'] ) || ( 0 === $i && ! $has_selected_design_variant );
						?>
						<button type="button"
							class="oc-design-variant-option oc-thumb-pending<?php echo $is_selected ? ' oc-selected' : ''; ?>"
							data-oc-design-variant="<?php echo esc_attr( $variant['id'] ); ?>"
							aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( __( 'Select %s artwork option', 'overcustomise' ), $variant['label'] ) ); ?>">
							<canvas class="oc-design-variant-canvas" data-oc-design-variant-thumb="<?php echo esc_attr( $variant['id'] ); ?>" aria-hidden="true"></canvas>
							<?php if ( ! empty( $variant['thumbLayers'] ) ) : ?>
								<div class="oc-design-variant-thumb" aria-hidden="true">
									<?php foreach ( $variant['thumbLayers'] as $thumb_layer ) :
										$thumb_style = sprintf(
											'left:%1$.4f%%;top:%2$.4f%%;width:%3$.4f%%;height:%4$.4f%%;',
											(float) ( $thumb_layer['x'] ?? 0 ),
											(float) ( $thumb_layer['y'] ?? 0 ),
											(float) ( $thumb_layer['w'] ?? 0 ),
											(float) ( $thumb_layer['h'] ?? 0 )
										);
										$thumb_colour = sanitize_hex_color( (string) ( $thumb_layer['color'] ?? '#111111' ) ) ?: '#111111';
										$thumb_text_style = $thumb_style
											. 'color:' . $thumb_colour . ';'
											. '--oc-thumb-font-size:' . (float) ( $thumb_layer['fontSize'] ?? 10 ) . ';'
											. 'font-size:' . (float) ( $thumb_layer['fontSize'] ?? 10 ) . 'px;'
											. 'font-family:' . wp_json_encode( (string) ( $thumb_layer['fontFamily'] ?? 'sans-serif' ) ) . ', sans-serif;'
											. 'font-weight:' . preg_replace( '/[^a-zA-Z0-9-]/', '', (string) ( $thumb_layer['fontWeight'] ?? 'normal' ) ) . ';'
											. 'font-style:' . preg_replace( '/[^a-zA-Z-]/', '', (string) ( $thumb_layer['fontStyle'] ?? 'normal' ) ) . ';';
										?>
										<?php if ( 'text' === (string) ( $thumb_layer['type'] ?? '' ) ) : ?>
											<span class="oc-design-variant-thumb-layer oc-design-variant-thumb-text" style="<?php echo esc_attr( $thumb_text_style ); ?>"><?php echo esc_html( $thumb_layer['text'] ?? '' ); ?></span>
										<?php elseif ( ! empty( $thumb_layer['url'] ) ) : ?>
											<img class="oc-design-variant-thumb-layer" src="<?php echo esc_url( $thumb_layer['url'], $oc_thumb_protocols ); ?>" alt="" loading="lazy" style="<?php echo esc_attr( $thumb_style ); ?>" />
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
							<?php elseif ( ! empty( $variant['thumbUrl'] ) ) : ?>
								<img src="<?php echo esc_url( $variant['thumbUrl'], $oc_thumb_protocols ); ?>" alt="" loading="lazy" />
							<?php endif; ?>
							<span><?php echo esc_html( $variant['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
					</div>
					<button type="button" class="oc-design-variant-carousel-arrow oc-design-variant-carousel-arrow--next" data-oc-design-variant-next aria-label="<?php esc_attr_e( 'Next artwork options', 'overcustomise' ); ?>">›</button>
					<div class="oc-design-variant-carousel-dots" data-oc-design-variant-dots></div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="oc-canvas-wrap" id="oc-canvas-wrap" aria-label="<?php esc_attr_e( 'Live customisation preview', 'overcustomise' ); ?>">
		<div class="oc-preview-label"><?php esc_html_e( 'Live preview', 'overcustomise' ); ?></div>
		<?php
		$first_area = $areas[0] ?? null;
		if ( $first_area && ! empty( $first_area->mockup_attachment_id ) ) {
			$mockup_src = wp_get_attachment_image_src( (int) $first_area->mockup_attachment_id, 'large' );
			$mockup_url = $mockup_src ? $mockup_src[0] : '';
			if ( $mockup_url ) : ?>
				<img id="oc-canvas-preview" src="<?php echo esc_url( $mockup_url ); ?>" alt="<?php esc_attr_e( 'Customisation preview', 'overcustomise' ); ?>" />
			<?php endif;
		}
		?>
	</div>

	<!-- Controls (one block per area) -->
	<?php $rendered_link_groups = []; ?>
	<?php foreach ( $areas as $i => $area ) :
		$area_layers = array_filter( $layers_by_area[ (int) $area->id ] ?? [], fn( $l ) => (bool) $l->visible );
		?>
		<div class="oc-area-controls" id="oc-area-panel-<?php echo esc_attr( $i ); ?>" data-area-index="<?php echo esc_attr( $i ); ?>">
			<div class="oc-layer-controls">
				<?php $is_engraving = ( $area->print_method ?? '' ) === 'engraving';
				foreach ( array_values( $area_layers ) as $layer ) :
					if ( (bool) ( $layer->locked ?? false ) ) continue; // Locked layers: no customer input
					$s         = OC_Cart::normalise_layer_settings( $layer->settings ?? [], sanitize_key( (string) ( $layer->type ?? '' ) ) );
					$link_group = trim( (string) ( $s['link_group'] ?? '' ) );
					if ( '' !== $link_group ) {
						$link_group_key = (string) $layer->type . '|' . $link_group;
						if ( isset( $rendered_link_groups[ $link_group_key ] ) ) continue;
						$rendered_link_groups[ $link_group_key ] = true;
					}
					$required  = ! empty( $s['required'] );
					$char_lim  = (int) ( $s['char_limit'] ?? 0 );
					$default   = $s['default_text'] ?? '';
					$alignment = $s['alignment']    ?? 'center';
					$default_font_size = absint( $s['default_font_size'] ?? 0 );
					$min_font_size     = absint( $s['min_font_size'] ?? 0 );
					$max_font_size     = absint( $s['max_font_size'] ?? 0 );
					$font_size_min     = max( 1, $min_font_size ?: 1 );
					$font_size_max     = max( $font_size_min, $max_font_size ?: 200 );
					$font_size_value   = min( $font_size_max, max( $font_size_min, $default_font_size ?: 24 ) );
					$default_colour    = sanitize_hex_color( (string) ( $s['default_color'] ?? '#000000' ) ) ?: '#000000';
					$allow_font_change   = ! array_key_exists( 'allow_font_change', $s ) || ! empty( $s['allow_font_change'] );
					$allow_colour_change = ! array_key_exists( 'allow_colour_change', $s ) || ! empty( $s['allow_colour_change'] );
					$allow_size_change   = ! empty( $s['allow_size_change'] );
					$allow_image_change  = ! array_key_exists( 'allow_image_change', $s ) || ! empty( $s['allow_image_change'] );
					$allow_image_filter_change = ! array_key_exists( 'allow_image_filter_change', $s ) || ! empty( $s['allow_image_filter_change'] );
					$allow_clipart_change = ! array_key_exists( 'allow_clipart_change', $s ) || ! empty( $s['allow_clipart_change'] );
					$enable_image_colour = ! empty( $s['enable_image_colour'] );
					if ( 'clipart' === $layer->type && ! $allow_clipart_change ) continue;
					$cg_ids    = $s['colour_groups'];
					$fg_ids    = $s['font_groups'];
					$clipart_groups = $s['clipart_groups'];
					$clipart_group_ids = array_values( array_filter( array_map( 'absint', $clipart_groups ) ) );
					$clipart_display = 'carousel' === (string) ( $s['clipart_display'] ?? 'grid' ) ? 'carousel' : 'grid';
					$image_filter_ids = array_values( array_intersect( $s['image_filter_ids'], $active_image_filter_ids ) );
					$default_image_filter_id = absint( $s['default_image_filter_id'] ?? 0 );
					if ( $default_image_filter_id && ! in_array( $default_image_filter_id, $image_filter_ids, true ) ) {
						$default_image_filter_id = 0;
					}

					// Colour list for this layer.
					$cg_ids = array_values( array_filter( array_map( 'absint', $cg_ids ) ) );
					$layer_colours = OC_DB::get_colours_for_groups( $cg_ids );
					if ( ! empty( $cg_ids ) && ! empty( $layer_colours ) ) {
						$allowed_hexes = array_map( fn( $colour ) => strtolower( (string) ( $colour->hex ?? '' ) ), $layer_colours );
						if ( ! in_array( strtolower( $default_colour ), $allowed_hexes, true ) ) {
							$default_colour = sanitize_hex_color( (string) ( $layer_colours[0]->hex ?? '#000000' ) ) ?: '#000000';
						}
					}
					$fg_ids = array_values( array_intersect( array_filter( array_map( 'absint', $fg_ids ) ), $valid_font_group_ids ) );
					$layer_fonts = $all_fonts;
					if ( ! empty( $fg_ids ) ) {
						$layer_fonts = [];
						foreach ( OC_DB::get_font_ids_for_groups( $fg_ids ) as $font_id ) {
							if ( isset( $fonts_by_id[ $font_id ] ) ) {
								$layer_fonts[] = $fonts_by_id[ $font_id ];
							}
						}
					}
					$layer_font_ids = array_map( static fn ( array $font ): int => (int) $font['id'], $layer_fonts );
					$default_font_id = absint( $s['default_font_id'] ?? 0 );
					if ( ! in_array( $default_font_id, $layer_font_ids, true ) ) {
						$default_font_id = (int) ( $layer_font_ids[0] ?? 0 );
					}
					?>
					<?php
					// These layer types either show their label inline or do not need a section header.
					$inline_label_types = [ 'text', 'textarea', 'image', 'clipmask', 'clipart', 'spotify' ];
					$show_header_label  = ! in_array( $layer->type, $inline_label_types, true );
					$show_required_in_header = $required && ! in_array( $layer->type, $inline_label_types, true );
					$default_attachment_id = absint( $s['default_attachment_id'] ?? 0 );
					if ( $default_attachment_id && ( ! OC_Upload_Handler::admin_default_attachment_is_valid( $default_attachment_id ) || ! str_starts_with( (string) get_post_mime_type( $default_attachment_id ), 'image/' ) ) ) {
						$default_attachment_id = 0;
					}
					$default_attachment_url = $default_attachment_id ? (string) wp_get_attachment_url( $default_attachment_id ) : '';
					?>
					<div class="oc-layer-section">
						<?php if ( in_array( $layer->type, [ 'image', 'clipmask' ], true ) && $default_attachment_url ) : ?>
							<span hidden data-oc-default-image="<?php echo esc_attr( $layer->id ); ?>" data-oc-default-image-id="<?php echo esc_attr( $default_attachment_id ); ?>" data-oc-default-image-url="<?php echo esc_url( $default_attachment_url ); ?>"></span>
						<?php endif; ?>
						<?php if ( $show_header_label || $show_required_in_header ) : ?>
						<div class="oc-layer-header">
							<?php if ( $show_header_label ) : ?>
								<span><?php echo esc_html( $layer->label ?: ucfirst( $layer->type ) ); ?></span>
							<?php endif; ?>
							<?php if ( $show_required_in_header ) : ?>
								<span class="oc-layer-required">* <?php esc_html_e( 'Required', 'overcustomise' ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>

						<div class="oc-layer-body">

							<?php if ( $layer->type === 'text' ) : ?>
								<div class="oc-control-group oc-control-group--side-label">
									<label for="oc-text-<?php echo esc_attr( $layer->id ); ?>"><?php echo esc_html( $layer->label ); ?><?php if ( $required ) echo ' *'; ?></label>
									<div class="oc-input-wrap">
										<input type="text"
											id="oc-text-<?php echo esc_attr( $layer->id ); ?>"
											name="oc_layer_inputs[<?php echo esc_attr( $layer->id ); ?>][value]"
											value=""
											placeholder="<?php echo esc_attr( $default ?: __( 'Enter text…', 'overcustomise' ) ); ?>"
											autocomplete="off"
											inputmode="text"
											<?php echo $char_lim > 0 ? 'maxlength="' . esc_attr( $char_lim ) . '"' : ''; ?>
											data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
										/>
										<span class="oc-char-counter" data-oc-char-counter="<?php echo esc_attr( $layer->id ); ?>" data-char-limit="<?php echo esc_attr( $char_lim ); ?>"></span>
									</div>
								</div>

							<?php elseif ( $layer->type === 'textarea' ) : ?>
								<div class="oc-control-group oc-control-group--side-label">
									<label for="oc-text-<?php echo esc_attr( $layer->id ); ?>"><?php echo esc_html( $layer->label ); ?><?php if ( $required ) echo ' *'; ?></label>
									<div class="oc-input-wrap">
										<textarea
											id="oc-text-<?php echo esc_attr( $layer->id ); ?>"
											name="oc_layer_inputs[<?php echo esc_attr( $layer->id ); ?>][value]"
											placeholder="<?php echo esc_attr( $default ?: __( 'Enter text…', 'overcustomise' ) ); ?>"
											inputmode="text"
											<?php echo $char_lim > 0 ? 'maxlength="' . esc_attr( $char_lim ) . '"' : ''; ?>
											data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
										></textarea>
										<span class="oc-char-counter" data-oc-char-counter="<?php echo esc_attr( $layer->id ); ?>" data-char-limit="<?php echo esc_attr( $char_lim ); ?>"></span>
									</div>
								</div>

							<?php elseif ( $layer->type === 'image' || $layer->type === 'clipmask' ) : ?>
									<div class="oc-artwork-wrap">
									<?php if ( $allow_image_change ) : ?>
										<div class="oc-upload-zone"
											data-oc-upload-zone="<?php echo esc_attr( $layer->id ); ?>">
									</div>
									<div class="oc-resolution-warning" data-oc-resolution-warning="<?php echo esc_attr( $layer->id ); ?>" style="display:none;"></div>
									<?php else : ?>
										<p class="oc-settings-empty"><?php esc_html_e( 'Image is fixed for this product.', 'overcustomise' ); ?></p>
									<?php endif; ?>
									<?php if ( 'image' === $layer->type && ! empty( $image_filter_ids ) && $allow_image_filter_change ) : ?>
										<?php $available_filters = array_filter( $all_image_filters, fn( $filter ) => in_array( (int) $filter->id, $image_filter_ids, true ) ); ?>
										<?php if ( ! empty( $available_filters ) ) : ?>
											<div class="oc-control-group oc-control-group--side-label" style="margin-top:10px;">
												<label for="oc-image-filter-<?php echo esc_attr( $layer->id ); ?>"><?php esc_html_e( 'Filter', 'overcustomise' ); ?></label>
												<select id="oc-image-filter-<?php echo esc_attr( $layer->id ); ?>" data-oc-layer-image-filter="<?php echo esc_attr( $layer->id ); ?>">
													<option value="0" <?php selected( $default_image_filter_id, 0 ); ?>><?php esc_html_e( 'Original', 'overcustomise' ); ?></option>
													<?php foreach ( $available_filters as $filter ) : ?>
														<option value="<?php echo esc_attr( (int) $filter->id ); ?>" <?php selected( $default_image_filter_id, (int) $filter->id ); ?>><?php echo esc_html( $filter->name ); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( 'image' === $layer->type && $enable_image_colour && ! $is_engraving && $allow_colour_change && ! empty( $image_filter_ids ) ) : ?>
										<div class="oc-control-group" style="margin-top:10px;">
											<label><?php echo 'embroidery' === (string) ( $area->print_method ?? '' ) ? esc_html__( 'Thread colour', 'overcustomise' ) : esc_html__( 'Artwork colour', 'overcustomise' ); ?></label>
											<?php if ( ! empty( $layer_colours ) ) : ?>
												<?php $render_colour_picker( $layer_colours, (int) $layer->id, $default_colour, 'artwork' ); ?>
											<?php elseif ( empty( $cg_ids ) ) : ?>
												<input type="color" value="<?php echo esc_attr( $default_colour ); ?>" data-oc-layer-color="<?php echo esc_attr( $layer->id ); ?>" />
											<?php else : ?>
												<p class="oc-settings-empty"><?php esc_html_e( 'No colours are available for this option.', 'overcustomise' ); ?></p>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>

							<?php elseif ( $layer->type === 'clipart' ) : ?>
								<?php
								$items = array_map( static function ( array $item ) {
									return (object) [
										'id' => $item['id'] ?? 0,
										'name' => $item['name'] ?? '',
										'url' => $item['url'] ?? '',
										'file_type' => $item['fileType'] ?? 'svg',
										'recolourable' => ! empty( $item['recolourable'] ),
										'group_names' => implode( '||', is_array( $item['groupNames'] ?? null ) ? $item['groupNames'] : [] ),
									];
								}, is_array( $clipart_by_layer[ (int) $layer->id ] ?? null ) ? $clipart_by_layer[ (int) $layer->id ] : [] );
								$group_names = [];
								foreach ( $items as $ci ) {
									if ( ! empty( $ci->group_names ) ) {
										foreach ( array_filter( array_map( 'trim', explode( '||', $ci->group_names ) ) ) as $gn ) {
											if ( ! in_array( $gn, $group_names, true ) ) {
												$group_names[] = $gn;
											}
										}
									}
								}
								sort( $group_names );
								?>
								<?php if ( count( $clipart_group_ids ) !== 1 ) : ?>
									<div class="oc-clipart-filters" data-oc-clipart-filters="<?php echo esc_attr( $layer->id ); ?>">
										<input type="search" class="oc-clipart-search" placeholder="<?php esc_attr_e( 'Search clipart…', 'overcustomise' ); ?>" data-oc-clipart-search="<?php echo esc_attr( $layer->id ); ?>" />
										<?php if ( ! empty( $group_names ) ) : ?>
										<select class="oc-clipart-category" data-oc-clipart-category="<?php echo esc_attr( $layer->id ); ?>">
											<option value=""><?php esc_html_e( 'All categories', 'overcustomise' ); ?></option>
											<?php foreach ( $group_names as $gn ) : ?>
												<option value="<?php echo esc_attr( $gn ); ?>"><?php echo esc_html( $gn ); ?></option>
											<?php endforeach; ?>
										</select>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( 'carousel' === $clipart_display ) : ?>
								<div class="oc-clipart-carousel" data-oc-clipart-carousel="<?php echo esc_attr( $layer->id ); ?>">
									<button type="button" class="oc-clipart-carousel-arrow oc-clipart-carousel-arrow--prev" data-oc-clipart-prev="<?php echo esc_attr( $layer->id ); ?>" aria-label="<?php esc_attr_e( 'Previous clipart options', 'overcustomise' ); ?>">&#8249;</button>
								<?php endif; ?>
								<div class="oc-clipart-grid<?php echo 'carousel' === $clipart_display ? ' oc-clipart-grid--carousel' : ''; ?>" data-oc-clipart-grid="<?php echo esc_attr( $layer->id ); ?>">
									<?php foreach ( $items as $ci ) :
										$curl = ! empty( $ci->url ) ? esc_url_raw( (string) $ci->url ) : '';
										if ( '' === $curl ) continue;
										$ci_group_names = $ci->group_names ? array_filter( array_map( 'trim', explode( '||', $ci->group_names ) ) ) : [];
										$ci_groups_attr = implode( '||', $ci_group_names );
										$ci_recolourable = property_exists( $ci, 'recolourable' ) ? (bool) $ci->recolourable : ( ( ! property_exists( $ci, 'colour_changeable' ) || (bool) $ci->colour_changeable ) && 'svg' === strtolower( (string) $ci->file_type ) );
										?>
										<button type="button" class="oc-clipart-item"
											data-oc-clipart="<?php echo esc_attr( $ci->id ); ?>"
											data-oc-layer-clipart="<?php echo esc_attr( $layer->id ); ?>"
											data-oc-clipart-url="<?php echo esc_attr( $curl ); ?>"
										data-oc-clipart-recolourable="<?php echo $ci_recolourable ? '1' : '0'; ?>"
										data-oc-clipart-groups="<?php echo esc_attr( $ci_groups_attr ); ?>"
										title="<?php echo esc_attr( $ci->name ); ?>"
										aria-label="<?php echo esc_attr( sprintf( __( 'Select %s clipart', 'overcustomise' ), $ci->name ) ); ?>"
										aria-pressed="false">
										<img src="<?php echo esc_url( $curl ); ?>" alt="<?php echo esc_attr( $ci->name ); ?>" loading="lazy" />
									</button>
									<?php endforeach; ?>
									<?php if ( empty( $items ) ) : ?>
										<p class="oc-clipart-empty"><?php esc_html_e( 'No clipart available.', 'overcustomise' ); ?></p>
									<?php endif; ?>
								</div>
								<?php if ( 'carousel' === $clipart_display ) : ?>
									<button type="button" class="oc-clipart-carousel-arrow oc-clipart-carousel-arrow--next" data-oc-clipart-next="<?php echo esc_attr( $layer->id ); ?>" aria-label="<?php esc_attr_e( 'Next clipart options', 'overcustomise' ); ?>">&#8250;</button>
									<div class="oc-clipart-carousel-dots" data-oc-clipart-dots="<?php echo esc_attr( $layer->id ); ?>" aria-label="<?php esc_attr_e( 'Clipart option pages', 'overcustomise' ); ?>"></div>
								</div>
								<?php endif; ?>

								<?php if ( ! $is_engraving && $allow_colour_change ) : ?>
									<div class="oc-control-group">
									<label><?php esc_html_e( 'Clipart colour', 'overcustomise' ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<?php $render_colour_picker( $layer_colours, (int) $layer->id, $default_colour, 'clipart' ); ?>
										<?php elseif ( empty( $cg_ids ) ) : ?>
											<input type="color" value="<?php echo esc_attr( $default_colour ); ?>"
												data-oc-layer-color="<?php echo esc_attr( $layer->id ); ?>" />
										<?php else : ?>
											<p class="oc-settings-empty"><?php esc_html_e( 'No colours are available for this option.', 'overcustomise' ); ?></p>
										<?php endif; ?>
									</div>
							<?php endif; ?>

							<?php elseif ( $layer->type === 'lineart' && ! $is_engraving ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Colour', 'overcustomise' ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<?php $render_colour_picker( $layer_colours, (int) $layer->id, $default_colour, 'lineart' ); ?>
									<?php elseif ( empty( $cg_ids ) ) : ?>
										<input type="color" value="#000000"
											data-oc-layer-color="<?php echo esc_attr( $layer->id ); ?>" />
									<?php else : ?>
										<p class="oc-settings-empty"><?php esc_html_e( 'No colours are available for this option.', 'overcustomise' ); ?></p>
									<?php endif; ?>
								</div>

							<?php elseif ( $layer->type === 'spotify' ) : ?>
								<div class="oc-control-group oc-control-group--side-label">
									<label for="oc-spotify-<?php echo esc_attr( $layer->id ); ?>"><?php esc_html_e( 'Spotify URL', 'overcustomise' ); ?><?php if ( $required ) echo ' *'; ?></label>
									<input type="url"
										id="oc-spotify-<?php echo esc_attr( $layer->id ); ?>"
										placeholder="https://open.spotify.com/track/…"
										autocomplete="off"
										inputmode="url"
										data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
										data-oc-layer-spotify="<?php echo esc_attr( $layer->id ); ?>" />
									<span class="oc-help-tooltip oc-spotify-modal-help" data-oc-tooltip="spotify-<?php echo esc_attr( $layer->id ); ?>">
										<button type="button" class="oc-help-toggle oc-spotify-modal-trigger" aria-haspopup="dialog" aria-controls="oc-spotify-share-dialog" aria-label="<?php esc_attr_e( 'Where is my Spotify link?', 'overcustomise' ); ?>">?</button>
									</span>
									<div class="oc-spotify-error" data-oc-spotify-error="<?php echo esc_attr( $layer->id ); ?>" style="display:none;" aria-live="polite"></div>
								</div>
							<?php endif; ?>

							<!-- Font picker (text / textarea) -->
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && ( ( $allow_font_change && ! empty( $layer_fonts ) ) || $allow_size_change ) ) : ?>
								<div class="oc-font-size-row">
							<?php endif; ?>
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && $allow_font_change && ! empty( $layer_fonts ) ) : ?>
								<div class="oc-control-group">
									<label for="oc-font-search-<?php echo esc_attr( $layer->id ); ?>"><?php esc_html_e( 'Font', 'overcustomise' ); ?></label>
									<select class="oc-font-native-select" data-oc-layer-font="<?php echo esc_attr( $layer->id ); ?>" aria-hidden="true" tabindex="-1">
										<?php foreach ( $layer_fonts as $font ) : ?>
											<option value="<?php echo esc_attr( $font['id'] ); ?>"
											<?php selected( $default_font_id, absint( $font['id'] ) ); ?>
												style="font-family:'<?php echo esc_attr( $font['name'] ); ?>';">
												<?php echo esc_html( $font['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<div class="oc-font-combobox" data-oc-font-combobox="<?php echo esc_attr( $layer->id ); ?>">
										<input type="search"
											id="oc-font-search-<?php echo esc_attr( $layer->id ); ?>"
											class="oc-font-search"
											placeholder="<?php esc_attr_e( 'Search fonts', 'overcustomise' ); ?>"
											autocomplete="off"
											role="combobox"
											aria-autocomplete="list"
											aria-expanded="false"
											aria-controls="oc-font-list-<?php echo esc_attr( $layer->id ); ?>"
											data-oc-font-search="<?php echo esc_attr( $layer->id ); ?>" />
										<div class="oc-font-options" id="oc-font-list-<?php echo esc_attr( $layer->id ); ?>" role="listbox" data-oc-font-list="<?php echo esc_attr( $layer->id ); ?>">
											<?php foreach ( $layer_fonts as $font ) : ?>
												<button type="button"
													class="oc-font-option"
													role="option"
													aria-selected="<?php echo absint( $s['default_font_id'] ?? 0 ) === absint( $font['id'] ) ? 'true' : 'false'; ?>"
													data-oc-font-option="<?php echo esc_attr( $font['id'] ); ?>"
													style="font-family:'<?php echo esc_attr( $font['name'] ); ?>';">
													<?php echo esc_html( $font['name'] ); ?>
												</button>
											<?php endforeach; ?>
											<div class="oc-font-no-results" data-oc-font-empty hidden><?php esc_html_e( 'No fonts found.', 'overcustomise' ); ?></div>
										</div>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && $allow_size_change ) : ?>
								<div class="oc-control-group" data-oc-font-size-control="<?php echo esc_attr( $layer->id ); ?>">
									<label for="oc-font-size-<?php echo esc_attr( $layer->id ); ?>" data-oc-font-size-label>
										<?php esc_html_e( 'Text size', 'overcustomise' ); ?>
										<span class="oc-range-value" data-oc-range-value="<?php echo esc_attr( $layer->id ); ?>"><?php echo esc_html( $font_size_value ); ?></span>
									</label>
									<input type="range"
										min="<?php echo esc_attr( $font_size_min ); ?>"
										max="<?php echo esc_attr( $font_size_max ); ?>"
										step="1"
										value="<?php echo esc_attr( $font_size_value ); ?>"
										id="oc-font-size-<?php echo esc_attr( $layer->id ); ?>"
										data-oc-layer-font-size="<?php echo esc_attr( $layer->id ); ?>" />
									<p class="oc-font-size-notice" data-oc-font-size-notice hidden><?php esc_html_e( 'This font is already at the largest size that fits your text.', 'overcustomise' ); ?></p>
								</div>
							<?php endif; ?>
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && ( ( $allow_font_change && ! empty( $layer_fonts ) ) || $allow_size_change ) ) : ?>
								</div>
							<?php endif; ?>

							<!-- Colour picker (text / textarea) — skipped for engraving. -->
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && ! $is_engraving && $allow_colour_change ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Text colour', 'overcustomise' ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<?php $render_colour_picker( $layer_colours, (int) $layer->id, $default_colour, 'text' ); ?>
									<?php elseif ( empty( $cg_ids ) ) : ?>
										<input type="color" value="<?php echo esc_attr( $default_colour ); ?>"
											data-oc-layer-color="<?php echo esc_attr( $layer->id ); ?>" />
									<?php else : ?>
										<p class="oc-settings-empty"><?php esc_html_e( 'No colours are available for this option.', 'overcustomise' ); ?></p>
									<?php endif; ?>
								</div>
							<?php endif; ?>

						</div><!-- .oc-layer-body -->
					</div><!-- .oc-layer-section -->
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>

	<!-- Hidden canvases — Fabric.js renders here; gallery <img> is updated via toDataURL() -->
	<div style="display:none;" aria-hidden="true">
		<?php foreach ( $areas as $i => $area ) : ?>
			<canvas id="oc-canvas-<?php echo esc_attr( $i ); ?>"></canvas>
		<?php endforeach; ?>
	</div>

	<!-- Carries serialised customisation data to WooCommerce add-to-cart -->
	<input type="hidden" name="_oc_customisation" id="oc-customisation-data" value="{}" />

	<?php if ( $has_spotify_layer ) : ?>
	<dialog id="oc-spotify-share-dialog" class="oc-spotify-share-dialog" aria-labelledby="oc-sp-title-text" aria-describedby="oc-sp-desc-text">
		<div class="oc-sp-modal-card">
			<div class="oc-sp-visual-section" aria-hidden="true">
				<div class="oc-sp-gradient-overlay"></div>
				<div class="oc-sp-album-art">
					<div class="oc-sp-album-art-overlay">
						<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="18" cy="5" r="3"></circle>
							<circle cx="6" cy="12" r="3"></circle>
							<circle cx="18" cy="19" r="3"></circle>
							<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
							<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
						</svg>
					</div>
				</div>
			</div>

			<div class="oc-sp-content-section">
				<button type="button" class="oc-sp-close-btn" data-oc-spotify-modal-close aria-label="<?php esc_attr_e( 'Close modal', 'overcustomise' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>

				<h2 id="oc-sp-title-text" class="oc-sp-title"><?php esc_html_e( 'Get your Share Link', 'overcustomise' ); ?></h2>
				<p id="oc-sp-desc-text" class="oc-sp-subtitle"><?php esc_html_e( 'Follow these simple steps to find your unique shareable link.', 'overcustomise' ); ?></p>

				<div class="oc-sp-steps">
					<div class="oc-sp-step-item"><div class="oc-sp-step-number">1</div><div class="oc-sp-step-content"><h3><?php esc_html_e( 'Navigate', 'overcustomise' ); ?></h3><p><?php esc_html_e( 'Open Spotify and go to your song, album, artist, playlist, episode, or show.', 'overcustomise' ); ?></p></div></div>
					<div class="oc-sp-step-item"><div class="oc-sp-step-number">2</div><div class="oc-sp-step-content"><h3><?php esc_html_e( 'Open Menu', 'overcustomise' ); ?></h3><p><?php esc_html_e( 'Click the three dots next to the title.', 'overcustomise' ); ?></p></div></div>
					<div class="oc-sp-step-item"><div class="oc-sp-step-number">3</div><div class="oc-sp-step-content"><h3><?php esc_html_e( 'Copy Link', 'overcustomise' ); ?></h3><p><?php echo wp_kses_post( __( 'Select <span class="oc-sp-highlight">Share</span>, then <span class="oc-sp-highlight">Copy Link</span>.', 'overcustomise' ) ); ?></p></div></div>
				</div>

				<div class="oc-sp-footer">
					<button type="button" class="oc-sp-action-btn" data-oc-spotify-modal-close><?php esc_html_e( 'Got it', 'overcustomise' ); ?></button>
				</div>
			</div>
		</div>
	</dialog>
	<?php endif; ?>

</div><!-- #oc-customiser-panel -->
