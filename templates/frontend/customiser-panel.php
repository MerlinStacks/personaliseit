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

$all_fonts          = OC_Font_Registry::get_fonts_for_js();
$all_colours        = OC_DB::get_colours( true );
$upload_dir         = wp_upload_dir();
$has_multiple_areas = count( $areas ) > 1;
$has_spotify_layer  = false;

foreach ( $layers as $layer ) {
	if ( 'spotify' === (string) $layer->type && (bool) $layer->visible && empty( $layer->locked ) ) {
		$has_spotify_layer = true;
		break;
	}
}
?>

<div id="oc-customiser-panel" class="oc-customiser-panel">

	<div id="oc-preflight-messages" class="oc-preflight-messages" hidden></div>

	<?php if ( $has_multiple_areas ) : ?>
	<div class="oc-area-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Customisation areas', 'overcustomise' ); ?>">
		<?php foreach ( $areas as $i => $area ) : ?>
			<button type="button" role="tab"
				class="oc-area-tab<?php echo 0 === $i ? ' oc-active' : ''; ?>"
				id="oc-area-tab-<?php echo esc_attr( $i ); ?>"
				data-area-index="<?php echo esc_attr( $i ); ?>"
				aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
				aria-controls="oc-area-panel-<?php echo esc_attr( $i ); ?>"
				<?php echo 0 === $i ? '' : 'tabindex="-1"'; ?>
			><?php echo esc_html( $area->label ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="oc-preview-toggle-wrap">
		<button type="button" class="oc-preview-toggle" id="oc-preview-toggle" aria-expanded="false">
			<?php esc_html_e( 'Show live preview', 'overcustomise' ); ?>
		</button>
	</div>

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
	<?php foreach ( $areas as $i => $area ) :
		$area_layers = array_filter( $layers_by_area[ (int) $area->id ] ?? [], fn( $l ) => (bool) $l->visible );
		if ( empty( $area_layers ) ) continue;
		?>
		<div class="oc-area-controls" id="oc-area-panel-<?php echo esc_attr( $i ); ?>" data-area-index="<?php echo esc_attr( $i ); ?>"
			<?php if ( $has_multiple_areas ) : ?>
				role="tabpanel" aria-labelledby="oc-area-tab-<?php echo esc_attr( $i ); ?>"
			<?php endif; ?>
			<?php echo 0 !== $i ? 'style="display:none;"' : ''; ?>>
			<div class="oc-layer-controls">
				<?php $is_engraving = ( $area->print_method ?? '' ) === 'engraving';
				foreach ( array_values( $area_layers ) as $layer ) :
					if ( (bool) ( $layer->locked ?? false ) ) continue; // Locked layers: no customer input
					$s         = json_decode( $layer->settings ?: '{}', true ) ?: [];
					$required  = ! empty( $s['required'] );
					$char_lim  = (int) ( $s['char_limit'] ?? 0 );
					$default   = $s['default_text'] ?? '';
					$alignment = $s['alignment']    ?? 'center';
					$default_font_size = absint( $s['default_font_size'] ?? 0 );
					$default_colour    = sanitize_hex_color( (string) ( $s['default_color'] ?? '#000000' ) ) ?: '#000000';
					$allow_font_change   = ! array_key_exists( 'allow_font_change', $s ) || ! empty( $s['allow_font_change'] );
					$allow_colour_change = ! array_key_exists( 'allow_colour_change', $s ) || ! empty( $s['allow_colour_change'] );
					$allow_size_change   = ! empty( $s['allow_size_change'] );
					$cg_ids    = $s['colour_groups'] ?? [];
					$fg_ids    = $s['font_groups']   ?? [];

					// Colour list for this layer.
					$cg_ids = array_map( 'intval', is_array( $cg_ids ) ? $cg_ids : [] );
					if ( ! empty( $cg_ids ) ) {
						global $wpdb;
						$placeholders = implode( ',', array_fill( 0, count( $cg_ids ), '%d' ) );
						$allowed_colour_ids = $wpdb->get_col(
							$wpdb->prepare(
								"SELECT colour_id FROM {$wpdb->prefix}oc_colour_group_items WHERE group_id IN ($placeholders)",
								...$cg_ids
							)
						);
						$allowed_colour_ids = array_map( 'intval', is_array( $allowed_colour_ids ) ? $allowed_colour_ids : [] );
						$layer_colours = array_values(
							array_filter(
								$all_colours,
								fn( $c ) => in_array( (int) ( $c->id ?? 0 ), $allowed_colour_ids, true )
							)
						);
					} else {
						$layer_colours = $all_colours;
					}
					$layer_fonts   = $all_fonts;
					?>
					<?php
					// For text/textarea layers, the layer name is shown inline with the input
					// (matching the Font row), so we suppress it in the section header to avoid
					// duplication — only the type badge remains.
					$inline_label_types = [ 'text', 'textarea', 'image', 'spotify' ];
					$show_header_label  = ! in_array( $layer->type, $inline_label_types, true );
					$show_required_in_header = $required && ! in_array( $layer->type, $inline_label_types, true );
					?>
					<div class="oc-layer-section">
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
								<div class="oc-control-group">
									<label for="oc-text-<?php echo esc_attr( $layer->id ); ?>"><?php echo esc_html( $layer->label ); ?><?php if ( $required ) echo ' *'; ?></label>
									<input type="text"
										id="oc-text-<?php echo esc_attr( $layer->id ); ?>"
										<?php if ( $char_lim ) echo 'maxlength="' . esc_attr( $char_lim ) . '"'; ?>
										placeholder="<?php echo esc_attr( $default ?: __( 'Enter text…', 'overcustomise' ) ); ?>"
										autocomplete="off"
										inputmode="text"
										data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
									/>
									<span class="oc-char-counter" data-oc-char-counter="<?php echo esc_attr( $layer->id ); ?>" data-char-limit="<?php echo esc_attr( $char_lim ); ?>"></span>
								</div>

							<?php elseif ( $layer->type === 'textarea' ) : ?>
								<div class="oc-control-group">
									<label for="oc-text-<?php echo esc_attr( $layer->id ); ?>"><?php echo esc_html( $layer->label ); ?><?php if ( $required ) echo ' *'; ?></label>
									<textarea
										id="oc-text-<?php echo esc_attr( $layer->id ); ?>"
										<?php if ( $char_lim ) echo 'maxlength="' . esc_attr( $char_lim ) . '"'; ?>
										placeholder="<?php echo esc_attr( $default ?: __( 'Enter text…', 'overcustomise' ) ); ?>"
										inputmode="text"
										data-oc-layer-text="<?php echo esc_attr( $layer->id ); ?>"
									><?php echo esc_textarea( $default ); ?></textarea>
									<span class="oc-char-counter" data-oc-char-counter="<?php echo esc_attr( $layer->id ); ?>" data-char-limit="<?php echo esc_attr( $char_lim ); ?>"></span>
								</div>

							<?php elseif ( $layer->type === 'image' ) : ?>
								<div class="oc-artwork-wrap">
									<?php OC_Tooltips::render( 'upload-' . $layer->id, sprintf( __( 'Upload your design artwork. SVG, PNG, or JPG up to %d MB.', 'overcustomise' ), (int) OC_Admin_Settings::get( 'max_upload_size_mb' ) ) ); ?>
									<div class="oc-upload-zone"
										data-oc-upload-zone="<?php echo esc_attr( $layer->id ); ?>">
								</div>
								<div class="oc-resolution-warning" data-oc-resolution-warning="<?php echo esc_attr( $layer->id ); ?>" style="display:none;"></div>
								</div>

							<?php elseif ( $layer->type === 'clipart' ) : ?>
								<?php
								$cg_ids = $s['clipart_groups'] ?? [];
								if ( ! empty( $cg_ids ) ) {
									global $wpdb;
									$phs   = implode( ',', array_fill( 0, count( $cg_ids ), '%d' ) );
									$items = $wpdb->get_results( $wpdb->prepare(
										"SELECT DISTINCT c.id, c.name, c.file_path, GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names FROM {$wpdb->prefix}oc_clipart c
										 JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
										 JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
										 WHERE gi.group_id IN ($phs) AND c.active = 1
										 GROUP BY c.id, c.name, c.file_path ORDER BY c.name ASC",
										...$cg_ids
									) ) ?: [];
								} else {
									global $wpdb;
									$items = $wpdb->get_results( "SELECT id, name, file_path, GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names FROM {$wpdb->prefix}oc_clipart c
									 LEFT JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
									 LEFT JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
									 WHERE c.active = 1
									 GROUP BY c.id, c.name, c.file_path ORDER BY c.name ASC" ) ?: [];
								}
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
								<?php OC_Tooltips::render( 'clipart-' . $layer->id, __( 'Select a pre-made design element to add to your product.', 'overcustomise' ) ); ?>
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
								<div class="oc-clipart-grid" data-oc-clipart-grid="<?php echo esc_attr( $layer->id ); ?>">
									<?php foreach ( $items as $ci ) :
										$curl = $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( $ci->file_path );
										$ci_group_names = $ci->group_names ? array_filter( array_map( 'trim', explode( '||', $ci->group_names ) ) ) : [];
										$ci_groups_attr = implode( '||', $ci_group_names );
										?>
										<button type="button" class="oc-clipart-item"
											data-oc-clipart="<?php echo esc_attr( $ci->id ); ?>"
											data-oc-layer-clipart="<?php echo esc_attr( $layer->id ); ?>"
											data-oc-clipart-url="<?php echo esc_attr( $curl ); ?>"
											data-oc-clipart-groups="<?php echo esc_attr( $ci_groups_attr ); ?>"
											aria-label="<?php echo esc_attr( sprintf( __( 'Select %s clipart', 'overcustomise' ), $ci->name ) ); ?>"
											aria-pressed="false"
											title="<?php echo esc_attr( $ci->name ); ?>">
											<img src="<?php echo esc_url( $curl ); ?>" alt="<?php echo esc_attr( $ci->name ); ?>" loading="lazy" />
										</button>
									<?php endforeach; ?>
									<?php if ( empty( $items ) ) : ?>
										<p class="oc-clipart-empty"><?php esc_html_e( 'No clipart available.', 'overcustomise' ); ?></p>
									<?php endif; ?>
								</div>

							<?php elseif ( $layer->type === 'lineart' && ! $is_engraving ) : ?>
								<?php OC_Tooltips::render( 'lineart-' . $layer->id, __( 'Choose a solid colour background for this area.', 'overcustomise' ) ); ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Colour', 'overcustomise' ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<div class="oc-colour-swatches">
											<?php foreach ( $layer_colours as $colour ) : ?>
											<button type="button" class="oc-colour-swatch"
												style="background:<?php echo esc_attr( $colour->hex ); ?>;"
												title="<?php echo esc_attr( $colour->name ); ?>"
												aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'overcustomise' ), $colour->name ) ); ?>"
												aria-pressed="false"
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
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && $allow_font_change && ! empty( $layer_fonts ) ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Font', 'overcustomise' ); ?><?php OC_Tooltips::render( 'font-' . $layer->id, __( 'Choose the font style for your text.', 'overcustomise' ) ); ?></label>
									<select data-oc-layer-font="<?php echo esc_attr( $layer->id ); ?>">
										<?php foreach ( $layer_fonts as $font ) : ?>
											<option value="<?php echo esc_attr( $font['id'] ); ?>"
												<?php selected( absint( $s['default_font_id'] ?? 0 ), absint( $font['id'] ) ); ?>
												style="font-family:'<?php echo esc_attr( $font['name'] ); ?>';">
												<?php echo esc_html( $font['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>

							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && $allow_size_change ) : ?>
								<div class="oc-control-group">
									<label for="oc-font-size-<?php echo esc_attr( $layer->id ); ?>"><?php esc_html_e( 'Text size', 'overcustomise' ); ?></label>
									<input type="number" min="1" step="1" value="<?php echo esc_attr( $default_font_size ?: 24 ); ?>" id="oc-font-size-<?php echo esc_attr( $layer->id ); ?>" data-oc-layer-font-size="<?php echo esc_attr( $layer->id ); ?>" />
								</div>
							<?php endif; ?>

							<!-- Colour picker (text / textarea) — skipped for engraving. -->
							<?php if ( in_array( $layer->type, [ 'text', 'textarea' ], true ) && ! $is_engraving && $allow_colour_change ) : ?>
								<div class="oc-control-group">
									<label><?php esc_html_e( 'Text colour', 'overcustomise' ); ?><?php OC_Tooltips::render( 'color-' . $layer->id, __( 'Pick a colour from the options below.', 'overcustomise' ) ); ?></label>
									<?php if ( ! empty( $layer_colours ) ) : ?>
										<div class="oc-colour-swatches">
											<?php foreach ( $layer_colours as $colour ) : ?>
										<button type="button" class="oc-colour-swatch<?php echo strtolower( (string) $colour->hex ) === strtolower( $default_colour ) ? ' oc-selected' : ''; ?>"
											style="background:<?php echo esc_attr( $colour->hex ); ?>;"
											title="<?php echo esc_attr( $colour->name ); ?>"
											aria-label="<?php echo esc_attr( sprintf( __( 'Select %s text colour', 'overcustomise' ), $colour->name ) ); ?>"
											aria-pressed="<?php echo strtolower( (string) $colour->hex ) === strtolower( $default_colour ) ? 'true' : 'false'; ?>"
											data-oc-layer-swatch="<?php echo esc_attr( $layer->id ); ?>"
											data-hex="<?php echo esc_attr( $colour->hex ); ?>">
											</button>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<input type="color" value="<?php echo esc_attr( $default_colour ); ?>"
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
