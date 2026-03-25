<?php
/**
 * Print Methods page — configure per-method settings for each decoration type.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Print_Methods {

	private const OPTION_KEY = 'oc_print_methods';

	/** Return settings for all print methods. */
	public static function get( string $method = '' ): mixed {
		$defaults = [
			'engraving' => [
				'label'           => __( 'Engraving', 'overcustomise' ),
				'dpi'             => 600,
				'colour_space'    => 'grayscale',
				'enabled'         => true,
				'notes'           => '',
			],
			'uv' => [
				'label'           => __( 'UV Printing', 'overcustomise' ),
				'dpi'             => 300,
				'colour_space'    => 'cmyk',
				'white_ink_layer' => false,
				'enabled'         => true,
				'notes'           => '',
			],
			'embroidery' => [
				'label'              => __( 'Embroidery', 'overcustomise' ),
				'dpi'                => 300,
				'colour_space'       => 'srgb',
				'max_colours'        => 8,
				'thread_brand'       => 'Madeira',
				'enabled'            => true,
				'notes'              => '',
			],
			'sublimation' => [
				'label'           => __( 'Sublimation', 'overcustomise' ),
				'dpi'             => 300,
				'colour_space'    => 'cmyk',
				'full_bleed'      => true,
				'enabled'         => true,
				'notes'           => '',
			],
		];

		$saved = get_option( self::OPTION_KEY, [] );
		$all   = [];

		foreach ( $defaults as $key => $def ) {
			$all[ $key ] = wp_parse_args( $saved[ $key ] ?? [], $def );
		}

		if ( '' !== $method ) {
			return $all[ $method ] ?? null;
		}

		return $all;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		if ( isset( $_POST['oc_print_methods_nonce'] ) ) {
			$this->save();
		}

		$methods = self::get();

		$method_icons = [
			'engraving'   => '⚡',
			'uv'          => '🎨',
			'embroidery'  => '🧵',
			'sublimation' => '🌡️',
		];
		?>
		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Print Methods', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Configure default settings for each decoration method.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<?php settings_errors( 'oc_print_methods' ); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'oc_save_print_methods', 'oc_print_methods_nonce' ); ?>

				<?php foreach ( $methods as $key => $m ) : ?>
					<div class="oc-method-card">
						<div class="oc-method-card-header">
							<h3>
								<span class="oc-method-icon"><?php echo $method_icons[ $key ] ?? '🖨️'; ?></span>
								<?php echo esc_html( $m['label'] ); ?>
							</h3>
							<label class="oc-toggle-label">
								<span class="oc-toggle">
									<input type="checkbox" name="<?php echo esc_attr( "oc_pm[{$key}][enabled]" ); ?>" value="1"
									       <?php checked( (bool) $m['enabled'] ); ?> />
									<span class="oc-toggle-slider"></span>
								</span>
								<span style="font-size:12px;color:var(--oc-gray-500);"><?php esc_html_e( 'Enabled', 'overcustomise' ); ?></span>
							</label>
						</div>
						<div class="oc-method-body">
							<div class="oc-form-grid">
								<div class="oc-form-row">
									<div class="oc-form-label">
										<label><?php esc_html_e( 'Output DPI', 'overcustomise' ); ?></label>
									</div>
									<div class="oc-form-field">
										<div style="display:flex;align-items:center;gap:8px;">
											<input type="number" name="<?php echo esc_attr( "oc_pm[{$key}][dpi]" ); ?>"
											       value="<?php echo esc_attr( $m['dpi'] ); ?>"
											       min="72" step="1" class="small-text oc-input" style="width:90px;" />
											<span style="font-size:13px;color:var(--oc-gray-400);">DPI</span>
										</div>
									</div>
								</div>
								<div class="oc-form-row">
									<div class="oc-form-label">
										<?php esc_html_e( 'Colour space', 'overcustomise' ); ?>
									</div>
									<div class="oc-form-field">
										<div style="display:flex;align-items:center;gap:8px;">
											<span class="oc-code"><?php echo esc_html( $m['colour_space'] ); ?></span>
											<span style="font-size:12px;color:var(--oc-gray-400);"><?php esc_html_e( 'Fixed per method', 'overcustomise' ); ?></span>
										</div>
									</div>
								</div>

								<?php if ( 'uv' === $key ) : ?>
									<div class="oc-form-row">
										<div class="oc-form-label">
											<?php esc_html_e( 'White ink layer', 'overcustomise' ); ?>
										</div>
										<div class="oc-form-field">
											<label class="oc-checkbox-label">
												<input type="checkbox" name="<?php echo esc_attr( "oc_pm[{$key}][white_ink_layer]" ); ?>" value="1"
												       <?php checked( (bool) $m['white_ink_layer'] ); ?> />
												<?php esc_html_e( 'Include a white underbase layer in UV print files', 'overcustomise' ); ?>
											</label>
										</div>
									</div>
								<?php endif; ?>

								<?php if ( 'embroidery' === $key ) : ?>
									<div class="oc-form-row">
										<div class="oc-form-label">
											<label><?php esc_html_e( 'Max thread colours', 'overcustomise' ); ?></label>
										</div>
										<div class="oc-form-field">
											<input type="number" name="<?php echo esc_attr( "oc_pm[{$key}][max_colours]" ); ?>"
											       value="<?php echo esc_attr( $m['max_colours'] ); ?>"
											       min="1" max="32" step="1" class="small-text oc-input" style="width:80px;" />
										</div>
									</div>
									<div class="oc-form-row">
										<div class="oc-form-label">
											<label><?php esc_html_e( 'Thread brand', 'overcustomise' ); ?></label>
										</div>
										<div class="oc-form-field">
											<select name="<?php echo esc_attr( "oc_pm[{$key}][thread_brand]" ); ?>" class="oc-select" style="width:200px;">
												<?php foreach ( [ 'Madeira', 'Isacord', 'Robison-Anton', 'Sulky', 'Brother' ] as $brand ) : ?>
													<option value="<?php echo esc_attr( $brand ); ?>" <?php selected( $m['thread_brand'], $brand ); ?>>
														<?php echo esc_html( $brand ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<p class="oc-form-help"><?php esc_html_e( 'Used for thread colour mapping in embroidery production briefs.', 'overcustomise' ); ?></p>
										</div>
									</div>
								<?php endif; ?>

								<?php if ( 'sublimation' === $key ) : ?>
									<div class="oc-form-row">
										<div class="oc-form-label">
											<?php esc_html_e( 'Full bleed', 'overcustomise' ); ?>
										</div>
										<div class="oc-form-field">
											<label class="oc-checkbox-label">
												<input type="checkbox" name="<?php echo esc_attr( "oc_pm[{$key}][full_bleed]" ); ?>" value="1"
												       <?php checked( (bool) $m['full_bleed'] ); ?> />
												<?php esc_html_e( 'Extend artwork to bleed edge on sublimation files', 'overcustomise' ); ?>
											</label>
										</div>
									</div>
								<?php endif; ?>

								<div class="oc-form-row">
									<div class="oc-form-label">
										<label><?php esc_html_e( 'Production notes', 'overcustomise' ); ?></label>
									</div>
									<div class="oc-form-field">
										<textarea name="<?php echo esc_attr( "oc_pm[{$key}][notes]" ); ?>"
										          rows="3" class="oc-textarea"
										><?php echo esc_textarea( $m['notes'] ); ?></textarea>
										<p class="oc-form-help"><?php esc_html_e( 'Appears on production briefs for this method.', 'overcustomise' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>

				<div style="margin-top:8px;">
					<button type="submit" class="oc-btn oc-btn-primary">
						<?php esc_html_e( 'Save Print Methods', 'overcustomise' ); ?>
					</button>
				</div>

			</form>
		</div>
		<?php
	}

	private function save(): void {
		if ( ! wp_verify_nonce( sanitize_key( $_POST['oc_print_methods_nonce'] ?? '' ), 'oc_save_print_methods' ) ) {
			add_settings_error( 'oc_print_methods', 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), 'error' );
			return;
		}

		$posted  = $_POST['oc_pm'] ?? [];
		$current = self::get();
		$allowed_methods = [ 'engraving', 'uv', 'embroidery', 'sublimation' ];
		$saved   = [];

		foreach ( $allowed_methods as $key ) {
			$p = $posted[ $key ] ?? [];
			$c = $current[ $key ];

			$saved[ $key ] = [
				'label'        => $c['label'],
				'colour_space' => $c['colour_space'],
				'enabled'      => ! empty( $p['enabled'] ),
				'dpi'          => max( 72, (int) ( $p['dpi'] ?? $c['dpi'] ) ),
				'notes'        => sanitize_textarea_field( $p['notes'] ?? '' ),
			];

			if ( 'uv' === $key ) {
				$saved[ $key ]['white_ink_layer'] = ! empty( $p['white_ink_layer'] );
			}

			if ( 'embroidery' === $key ) {
				$brands = [ 'Madeira', 'Isacord', 'Robison-Anton', 'Sulky', 'Brother' ];
				$saved[ $key ]['max_colours']  = max( 1, min( 32, (int) ( $p['max_colours'] ?? 8 ) ) );
				$saved[ $key ]['thread_brand'] = in_array( $p['thread_brand'] ?? '', $brands, true )
					? $p['thread_brand']
					: 'Madeira';
			}

			if ( 'sublimation' === $key ) {
				$saved[ $key ]['full_bleed'] = ! empty( $p['full_bleed'] );
			}
		}

		update_option( self::OPTION_KEY, $saved );
		add_settings_error( 'oc_print_methods', 'saved', __( 'Print method settings saved.', 'overcustomise' ), 'success' );
	}
}
