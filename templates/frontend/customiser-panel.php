<?php
/**
 * Frontend customiser panel — vanilla JS edition.
 * Variables: $design, $areas, $layers
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $areas ) ) return;

$layers_by_area = [];
foreach ( $layers as $layer ) {
	$layers_by_area[ (int) $layer->area_id ][] = $layer;
}

$all_fonts   = OC_Font_Registry::get_fonts_for_js();
$all_colours = OC_DB::get_colours( true );
$upload_dir  = wp_upload_dir();
?>

<div id="oc-customiser-panel" class="oc-customiser-panel">

	<?php if ( count( $areas ) > 1 ) : ?>
	<div class="oc-area-tabs" role="tablist">
		<?php foreach ( $areas as $i => $area ) : ?>
			<button type="button" role="tab"
				class="oc-area-tab<?php echo 0 === $i ? ' oc-active' : ''; ?>"
				data-area-index="<?php echo esc_attr( $i ); ?>"
				aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
			><?php echo esc_html( $area->label ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<!-- Controls (one block per area) -->
	<?php foreach ( $areas as $i => $area ) :
		$area_layers = array_filter( $layers_by_area[ (int) $area->id ] ?? [], fn( $l ) => (bool) $l->visible );
		if ( empty( $area_layers ) ) continue;
		?>
		<div class="oc-area-controls" data-area-index="<?php echo esc_attr( $i ); ?>"
			<?php echo 0 !== $i ? 'style="display:none;"' : ''; ?>>
			<div class="oc-layer-controls">
				<?php foreach ( array_values( $area_layers ) as $layer ) :
					$s         = json_decode( $layer->settings ?: '{}', true ) ?: [];
					$required  = ! empty( $s['required'] );
					$char_lim  = (int) ( $s['char_limit'] ?? 0 );
					$default   = $s['default_text'] ?? '';
					$alignment = $s['alignment']    ?? 'center';
					$cg_ids    = $s['colour_groups'] ?? [];
					$fg_ids    = $s['font_groups']   ?? [];

					// Colour list for this layer.
					$layer_colours = ! empty( $cg_ids )
						? array_filter( $all_colours, fn( $c ) => false ) // TODO: filter by group
						: $all_colours;
					// For now show all colours (group-filtering can be added later).
					$layer_colours = $all_colours;
					$layer_fonts   = $all_fonts;
					?>
					<div class="oc-layer-section">
						<div class="oc-layer-header">
							<span><?php echo esc_html( $layer->label ?: ucfirst( $layer->type ) ); ?></span>
							<span class="oc-layer-type-badge"><?php echo esc_html( $layer->type ); ?></span>
							<?php if ( $required ) : ?>
								<span class="oc-layer-required">* <?php esc_html_e( 'Required', 'overcustomise' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="oc-layer-body">

							<?php if ( $layer->type === 'text' ) : ?>
								<div class="oc-control-group">
									<label><?php echo esc_html( $layer->label ); ?><?php if ( $required ) echo ' *'; ?></label>
									<input type="text"
										<?php if ( $char_lim ) echo 'maxlength="' . esc_attr( $char_lim ) . '"'; ?>
										placeholder="<?php echo esc_attr( $default ?: __( 'Enter text…', 'overcustomise' ) ); ?>"
										autocomplete="off"
										data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
									/>
								</div>

							<?php elseif ( $layer->type === 'textarea' ) : ?>
								<div class="oc-control-group">
									<label><?php echo esc_html( $layer->label ); ?></label>
									<textarea
										<?php if ( $char_lim ) echo 'maxlength="' . esc_attr( $char_lim ) . '"'; ?>
										placeholder="<?php echo esc_attr( $default ?: __( 'Enter text…', 'overcustomise' ) ); ?>"
										data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
									><?php echo esc_textarea( $default ); ?></textarea>
								</div>

							<?php elseif ( $layer->type === 'image' ) : ?>
								<div class="oc-artwork-wrap">
									<div class="oc-upload-zone"
										data-oc-upload-zone="<?php echo esc_attr( $layer->id ); ?>">
									</div>
									<div class="oc-artwork-preview" style="display:none;">
										<img class="oc-artwork-thumb" src="" alt="" />
										<button type="button" class="oc-artwork-remove"
											data-oc-remove-image="<?php echo esc_attr( $layer->id ); ?>">
											<?php esc_html_e( 'Remove', 'overcustomise' ); ?>
										</button>
									</div>
								</div>

							<?php elseif ( $layer->type === 'clipart' ) : ?>
								<?php
								$cg_ids = $s['clipart_groups'] ?? [];
								if ( ! empty( $cg_ids ) ) {
									global $wpdb;
									$phs   = implode( ',', array_fill( 0, count( $cg_ids ), '%d' ) );
									$items = $wpdb->get_results( $wpdb->prepare(
										"SELECT DISTINCT c.id, c.name, c.file_path FROM {$wpdb->prefix}oc_clipart c
										 JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
										 WHERE gi.group_id IN ($phs) AND c.active = 1 ORDER BY c.name ASC",
										...$cg_ids
									) ) ?: [];
								} else {
									global $wpdb;
									$items = $wpdb->get_results( "SELECT id, name, file_path FROM {$wpdb->prefix}oc_clipart WHERE active = 1 ORDER BY name ASC" ) ?: [];
								}
								?>
								<div class="oc-clipart-grid">
									<?php foreach ( $items as $ci ) :
										$curl = $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( $ci->file_path ); ?>
										<button type="button" class="oc-clipart-item"
											data-oc-clipart="<?php echo esc_attr( $ci->id ); ?>"
											data-oc-layer-clipart="<?php echo esc_attr( $layer->id ); ?>"
											data-oc-clipart-url="<?php echo esc_attr( $curl ); ?>"
											title="<?php echo esc_attr( $ci->name ); ?>">
											<img src="<?php echo esc_url( $curl ); ?>" alt="<?php echo esc_attr( $ci->name ); ?>" loading="lazy" />
										</button>
									<?php endforeach; ?>
									<?php if ( empty( $items ) ) : ?>
										<p class="oc-clipart-empty"><?php esc_html_e( 'No clipart available.', 'overcustomise' ); ?></p>
									<?php endif; ?>
								</div>

							<?php elseif ( $layer->type === 'lineart' ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Colour', 'overcustomise' ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<div class="oc-colour-swatches">
											<?php foreach ( $layer_colours as $colour ) : ?>
												<button type="button" class="oc-colour-swatch"
													style="background:<?php echo esc_attr( $colour->hex ); ?>;"
													title="<?php echo esc_attr( $colour->name ); ?>"
													data-oc-layer-swatch="<?php echo esc_attr( $layer->id ); ?>"
													data-hex="<?php echo esc_attr( $colour->hex ); ?>">
												</button>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<input type="color" value="#000000"
											data-oc-layer-color="<?php echo esc_attr( $layer->id ); ?>" />
									<?php endif; ?>
								</div>

							<?php elseif ( $layer->type === 'spotify' ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Spotify URL', 'overcustomise' ); ?></label>
									<input type="url"
										placeholder="https://open.spotify.com/track/…"
										data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>" />
								</div>
							<?php endif; ?>

							<!-- Font picker (text / textarea) -->
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && ! empty( $layer_fonts ) ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Font', 'overcustomise' ); ?></label>
									<select data-oc-layer-font="<?php echo esc_attr( $layer->id ); ?>">
										<?php foreach ( $layer_fonts as $font ) : ?>
											<option value="<?php echo esc_attr( $font['id'] ); ?>"
												style="font-family:'<?php echo esc_attr( $font['name'] ); ?>';">
												<?php echo esc_html( $font['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>

							<!-- Colour picker (text / textarea) -->
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Text colour', 'overcustomise' ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<div class="oc-colour-swatches">
											<?php foreach ( $layer_colours as $colour ) : ?>
												<button type="button" class="oc-colour-swatch"
													style="background:<?php echo esc_attr( $colour->hex ); ?>;"
													title="<?php echo esc_attr( $colour->name ); ?>"
													data-oc-layer-swatch="<?php echo esc_attr( $layer->id ); ?>"
													data-hex="<?php echo esc_attr( $colour->hex ); ?>">
												</button>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<input type="color" value="#000000"
											data-oc-layer-color="<?php echo esc_attr( $layer->id ); ?>" />
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

</div><!-- #oc-customiser-panel -->
