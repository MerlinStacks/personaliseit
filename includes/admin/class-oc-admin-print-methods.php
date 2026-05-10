<?php
/**
 * Print Methods page - configure per-method settings for each decoration type.
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
				'label'        => __( 'Engraving', 'overcustomise' ),
				'dpi'          => 600,
				'colour_space' => 'grayscale',
				'enabled'      => true,
				'notes'        => '',
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
				'label'        => __( 'Embroidery', 'overcustomise' ),
				'dpi'          => 300,
				'colour_space' => 'srgb',
				'max_colours'  => 8,
				'thread_brand' => 'Madeira',
				'enabled'      => true,
				'notes'        => '',
			],
			'sublimation' => [
				'label'        => __( 'Sublimation', 'overcustomise' ),
				'dpi'          => 300,
				'colour_space' => 'cmyk',
				'full_bleed'   => true,
				'enabled'      => true,
				'notes'        => '',
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

		$methods          = self::get();
		$allowed_methods  = array_keys( $methods );
		$requested_method = isset( $_GET['method'] ) ? sanitize_key( wp_unslash( $_GET['method'] ) ) : '';
		$active_method    = in_array( $requested_method, $allowed_methods, true ) ? $requested_method : $allowed_methods[0];
		$active_settings  = $methods[ $active_method ];
		$form_action      = add_query_arg(
			[
				'page'   => 'overcustomise-print-methods',
				'method' => $active_method,
			],
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Print Methods', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Configure default settings for each decoration method.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<?php settings_errors( 'oc_print_methods' ); ?>

			<div class="oc-tabs-bar">
				<?php foreach ( $methods as $key => $m ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'overcustomise-print-methods', 'method' => $key ], admin_url( 'admin.php' ) ) ); ?>"
					   class="oc-tab<?php echo $active_method === $key ? ' oc-tab--active' : ''; ?>">
						<?php echo esc_html( $m['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<form method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php wp_nonce_field( 'oc_save_print_methods', 'oc_print_methods_nonce' ); ?>
				<input type="hidden" name="oc_active_method" value="<?php echo esc_attr( $active_method ); ?>" />

				<?php $this->render_method_card( $active_method, $active_settings ); ?>

				<div style="margin-top:8px;">
					<button type="submit" class="oc-btn oc-btn-primary">
						<?php
						printf(
							/* translators: %s: print method label */
							esc_html__( 'Save %s Settings', 'overcustomise' ),
							esc_html( $active_settings['label'] )
						);
						?>
					</button>
				</div>

			</form>
		</div>
		<?php
	}

	private function render_method_card( string $key, array $m ): void {
		?>
		<div class="oc-method-card">
			<div class="oc-method-card-header">
				<h3><?php echo esc_html( $m['label'] ); ?></h3>
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
							<?php esc_html_e( 'Colour space', 'overcustomise' ); ?><?php OC_Tooltips::render( 'colour-space-' . $key, __( 'The colour model used for output files. Fixed per print method and cannot be changed.', 'overcustomise' ) ); ?>
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
								<?php esc_html_e( 'White ink layer', 'overcustomise' ); ?><?php OC_Tooltips::render( 'white-ink', __( 'Adds a white underbase layer beneath colours, required for dark or transparent substrates.', 'overcustomise' ) ); ?>
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
		<?php
	}

	private function save(): void {
		if ( ! wp_verify_nonce( sanitize_key( $_POST['oc_print_methods_nonce'] ?? '' ), 'oc_save_print_methods' ) ) {
			add_settings_error( 'oc_print_methods', 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), 'error' );
			return;
		}

		$posted_all      = $_POST['oc_pm'] ?? [];
		$current         = self::get();
		$allowed_methods = array_keys( $current );
		$active_method   = sanitize_key( $_POST['oc_active_method'] ?? '' );

		if ( ! in_array( $active_method, $allowed_methods, true ) ) {
			$active_method = $allowed_methods[0];
		}

		$posted = [];
		if ( isset( $posted_all[ $active_method ] ) && is_array( $posted_all[ $active_method ] ) ) {
			$posted = $posted_all[ $active_method ];
		}

		$saved                   = $current;
		$saved[ $active_method ] = $this->sanitize_method_settings( $active_method, $posted, $current[ $active_method ] );

		update_option( self::OPTION_KEY, $saved );
		add_settings_error( 'oc_print_methods', 'saved', __( 'Print method settings saved.', 'overcustomise' ), 'success' );
	}

	private function sanitize_method_settings( string $key, array $posted, array $current ): array {
		$sanitised = [
			'label'        => $current['label'],
			'colour_space' => $current['colour_space'],
			'enabled'      => ! empty( $posted['enabled'] ),
			'dpi'          => max( 72, (int) ( $posted['dpi'] ?? $current['dpi'] ) ),
			'notes'        => sanitize_textarea_field( wp_unslash( (string) ( $posted['notes'] ?? '' ) ) ),
		];

		if ( 'uv' === $key ) {
			$sanitised['white_ink_layer'] = ! empty( $posted['white_ink_layer'] );
		}

		if ( 'embroidery' === $key ) {
			$brands = [ 'Madeira', 'Isacord', 'Robison-Anton', 'Sulky', 'Brother' ];
			$brand  = sanitize_text_field( wp_unslash( (string) ( $posted['thread_brand'] ?? '' ) ) );

			$sanitised['max_colours']  = max( 1, min( 32, (int) ( $posted['max_colours'] ?? $current['max_colours'] ) ) );
			$sanitised['thread_brand'] = in_array( $brand, $brands, true ) ? $brand : $current['thread_brand'];
		}

		if ( 'sublimation' === $key ) {
			$sanitised['full_bleed'] = ! empty( $posted['full_bleed'] );
		}

		return $sanitised;
	}
}
