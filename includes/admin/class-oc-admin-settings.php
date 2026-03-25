<?php
/**
 * Settings page — file retention, upload limits, embroidery paths, print defaults.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Settings {

	private const OPTION_KEY = 'oc_settings';

	/** Return the full settings array with defaults applied. */
	public static function get( string $key = '' ): mixed {
		$defaults = [
			// General.
			'flat_rate_default'      => '0.00',

			// File management.
			'file_retention_days'    => 90,
			'max_upload_size_mb'     => 10,
			'allowed_upload_formats' => [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg' ],

			// Embroidery.
			'python_binary'          => 'python3',
			'pyembroidery_cli_path'  => '',
			'embroidery_fallback'    => 'auto',  // 'auto' | 'force_tier2'

			// Print defaults.
			'bleed_mm'               => 3,
			'crop_mark_style'        => 'standard', // 'standard' | 'none'
			'icc_engraving'          => 'GrayGamma2.2',
			'icc_uv'                 => 'ISOcoated_v2_300_eci',
			'icc_sublimation'        => 'ISOcoated_v2_300_eci',
		];

		$saved = get_option( self::OPTION_KEY, [] );
		$all   = wp_parse_args( $saved, $defaults );

		if ( '' !== $key ) {
			return $all[ $key ] ?? null;
		}

		return $all;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		// Handle save.
		if ( isset( $_POST['oc_settings_nonce'] ) ) {
			$this->save();
		}

		$s = self::get();
		?>
		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Settings', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'File retention, upload limits, embroidery paths and print defaults.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<?php settings_errors( 'oc_settings' ); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'oc_save_settings', 'oc_settings_nonce' ); ?>

				<!-- ── General ──────────────────────────────────────────── -->
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'General', 'overcustomise' ); ?></h2>
					</div>
					<div class="oc-card-body">
						<div class="oc-form-grid">
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_flat_rate_default"><?php esc_html_e( 'Default flat rate (AUD)', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<div>
										<input type="number" id="oc_flat_rate_default" name="oc_flat_rate_default"
										       value="<?php echo esc_attr( $s['flat_rate_default'] ); ?>"
										       min="0" step="0.01" class="small-text oc-input" style="width:100px;" />
									</div>
									<p class="oc-form-help"><?php esc_html_e( 'Applied per item unless overridden on the product config.', 'overcustomise' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ── File Management ──────────────────────────────────── -->
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'File Management', 'overcustomise' ); ?></h2>
					</div>
					<div class="oc-card-body">
						<div class="oc-form-grid">
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_file_retention_days"><?php esc_html_e( 'Print file retention', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<div style="display:flex;align-items:center;gap:8px;">
										<input type="number" id="oc_file_retention_days" name="oc_file_retention_days"
										       value="<?php echo esc_attr( $s['file_retention_days'] ); ?>"
										       min="1" step="1" class="small-text oc-input" style="width:80px;" />
										<span style="font-size:13px;color:var(--oc-gray-500);"><?php esc_html_e( 'days', 'overcustomise' ); ?></span>
									</div>
									<p class="oc-form-help"><?php esc_html_e( 'Generated print files are automatically deleted after this many days. Default: 90.', 'overcustomise' ); ?></p>
								</div>
							</div>
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_max_upload_size_mb"><?php esc_html_e( 'Max customer upload size', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<div style="display:flex;align-items:center;gap:8px;">
										<input type="number" id="oc_max_upload_size_mb" name="oc_max_upload_size_mb"
										       value="<?php echo esc_attr( $s['max_upload_size_mb'] ); ?>"
										       min="1" step="1" class="small-text oc-input" style="width:80px;" />
										<span style="font-size:13px;color:var(--oc-gray-500);"><?php esc_html_e( 'MB', 'overcustomise' ); ?></span>
									</div>
								</div>
							</div>
							<div class="oc-form-row">
								<div class="oc-form-label">
									<?php esc_html_e( 'Allowed upload formats', 'overcustomise' ); ?>
								</div>
								<div class="oc-form-field">
									<div class="oc-checkbox-group">
										<?php
										$all_formats = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg' ];
										foreach ( $all_formats as $fmt ) :
											$checked = in_array( $fmt, (array) $s['allowed_upload_formats'], true );
											?>
											<label class="oc-checkbox-label">
												<input type="checkbox" name="oc_allowed_upload_formats[]"
												       value="<?php echo esc_attr( $fmt ); ?>"
												       <?php checked( $checked ); ?> />
												<?php echo esc_html( strtoupper( $fmt ) ); ?>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ── Embroidery ───────────────────────────────────────── -->
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Embroidery', 'overcustomise' ); ?></h2>
					</div>
					<div class="oc-card-body">
						<?php $this->render_python_status(); ?>
						<div class="oc-form-grid">
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_python_binary"><?php esc_html_e( 'Python binary', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<input type="text" id="oc_python_binary" name="oc_python_binary"
									       value="<?php echo esc_attr( $s['python_binary'] ); ?>"
									       class="regular-text oc-input" placeholder="python3" />
									<p class="oc-form-help"><?php esc_html_e( 'Full path or command name (e.g. python3 or /usr/bin/python3).', 'overcustomise' ); ?></p>
								</div>
							</div>
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_pyembroidery_cli_path"><?php esc_html_e( 'pyembroidery-CLI path', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<input type="text" id="oc_pyembroidery_cli_path" name="oc_pyembroidery_cli_path"
									       value="<?php echo esc_attr( $s['pyembroidery_cli_path'] ); ?>"
									       class="regular-text oc-input" placeholder="/path/to/pyemb_convert.py" />
									<p class="oc-form-help"><?php esc_html_e( 'Full path to the pyembroidery conversion script. Leave blank to disable Tier 1 auto-DST.', 'overcustomise' ); ?></p>
								</div>
							</div>
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_embroidery_fallback"><?php esc_html_e( 'Digitising fallback', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<select id="oc_embroidery_fallback" name="oc_embroidery_fallback" class="oc-select">
										<option value="auto" <?php selected( $s['embroidery_fallback'], 'auto' ); ?>>
											<?php esc_html_e( 'Auto-detect (use Tier 1 if available, fall back to Tier 2)', 'overcustomise' ); ?>
										</option>
										<option value="force_tier2" <?php selected( $s['embroidery_fallback'], 'force_tier2' ); ?>>
											<?php esc_html_e( 'Always use Tier 2 (production brief + manual DST upload)', 'overcustomise' ); ?>
										</option>
									</select>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ── Print Defaults ───────────────────────────────────── -->
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Print Defaults', 'overcustomise' ); ?></h2>
					</div>
					<div class="oc-card-body">
						<div class="oc-form-grid">
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_bleed_mm"><?php esc_html_e( 'Bleed size', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<div style="display:flex;align-items:center;gap:8px;">
										<input type="number" id="oc_bleed_mm" name="oc_bleed_mm"
										       value="<?php echo esc_attr( $s['bleed_mm'] ); ?>"
										       min="0" step="0.5" class="small-text oc-input" style="width:80px;" />
										<span style="font-size:13px;color:var(--oc-gray-500);">mm</span>
									</div>
								</div>
							</div>
							<div class="oc-form-row">
								<div class="oc-form-label">
									<label for="oc_crop_mark_style"><?php esc_html_e( 'Crop mark style', 'overcustomise' ); ?></label>
								</div>
								<div class="oc-form-field">
									<select id="oc_crop_mark_style" name="oc_crop_mark_style" class="oc-select" style="width:220px;">
										<option value="standard" <?php selected( $s['crop_mark_style'], 'standard' ); ?>>
											<?php esc_html_e( 'Standard', 'overcustomise' ); ?>
										</option>
										<option value="none" <?php selected( $s['crop_mark_style'], 'none' ); ?>>
											<?php esc_html_e( 'None', 'overcustomise' ); ?>
										</option>
									</select>
								</div>
							</div>
							<div class="oc-form-row">
								<div class="oc-form-label">
									<?php esc_html_e( 'ICC profiles', 'overcustomise' ); ?>
								</div>
								<div class="oc-form-field">
									<div style="display:flex;flex-direction:column;gap:8px;">
										<div style="display:flex;align-items:center;gap:10px;">
											<span style="font-size:12px;color:var(--oc-gray-500);width:90px;"><?php esc_html_e( 'Engraving', 'overcustomise' ); ?></span>
											<input type="text" name="oc_icc_engraving" value="<?php echo esc_attr( $s['icc_engraving'] ); ?>" class="regular-text oc-input" />
										</div>
										<div style="display:flex;align-items:center;gap:10px;">
											<span style="font-size:12px;color:var(--oc-gray-500);width:90px;"><?php esc_html_e( 'UV Printing', 'overcustomise' ); ?></span>
											<input type="text" name="oc_icc_uv" value="<?php echo esc_attr( $s['icc_uv'] ); ?>" class="regular-text oc-input" />
										</div>
										<div style="display:flex;align-items:center;gap:10px;">
											<span style="font-size:12px;color:var(--oc-gray-500);width:90px;"><?php esc_html_e( 'Sublimation', 'overcustomise' ); ?></span>
											<input type="text" name="oc_icc_sublimation" value="<?php echo esc_attr( $s['icc_sublimation'] ); ?>" class="regular-text oc-input" />
										</div>
									</div>
									<p class="oc-form-help" style="margin-top:6px;"><?php esc_html_e( 'ICC profile name or path embedded in generated PDFs.', 'overcustomise' ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<div class="oc-card-footer">
						<button type="submit" class="oc-btn oc-btn-primary">
							<?php esc_html_e( 'Save Settings', 'overcustomise' ); ?>
						</button>
					</div>
				</div>

			</form>
		</div>
		<?php
	}

	/** Show Python availability status notice. */
	private function render_python_status(): void {
		$python  = OC_Admin_Settings::get( 'python_binary' ) ?: 'python3';
		$cli     = OC_Admin_Settings::get( 'pyembroidery_cli_path' );
		$py_ok   = $this->check_python( $python );
		$cli_ok  = $cli && file_exists( $cli );

		if ( $py_ok && $cli_ok ) {
			$msg   = __( 'Tier 1 auto-DST is available (Python found, pyembroidery-CLI path set).', 'overcustomise' );
			$class = 'notice-success';
		} elseif ( $py_ok ) {
			$msg   = __( 'Python found but pyembroidery-CLI path is not set — Tier 2 (production brief) will be used.', 'overcustomise' );
			$class = 'notice-warning';
		} else {
			$msg   = __( 'Python not found — Tier 2 (production brief + manual DST upload) will be used for embroidery.', 'overcustomise' );
			$class = 'notice-info';
		}

		printf(
			'<div class="notice %s inline"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $msg )
		);
	}

	/** Check if a given python binary is callable. */
	private function check_python( string $binary ): bool {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$binary = escapeshellcmd( $binary );
		exec( "{$binary} --version 2>&1", $output, $code );
		return 0 === $code;
	}

	/** Sanitise and persist settings. */
	private function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['oc_settings_nonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_POST['oc_settings_nonce'] ), 'oc_save_settings' )
		) {
			add_settings_error( 'oc_settings', 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), 'error' );
			return;
		}

		$allowed_formats = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg' ];
		$posted_formats  = isset( $_POST['oc_allowed_upload_formats'] )
			? array_intersect( (array) $_POST['oc_allowed_upload_formats'], $allowed_formats )
			: [];

		$settings = [
			'flat_rate_default'      => number_format( (float) ( $_POST['oc_flat_rate_default'] ?? 0 ), 2, '.', '' ),
			'file_retention_days'    => max( 1, (int) ( $_POST['oc_file_retention_days'] ?? 90 ) ),
			'max_upload_size_mb'     => max( 1, (int) ( $_POST['oc_max_upload_size_mb'] ?? 10 ) ),
			'allowed_upload_formats' => array_values( $posted_formats ),
			'python_binary'          => sanitize_text_field( $_POST['oc_python_binary'] ?? 'python3' ),
			'pyembroidery_cli_path'  => sanitize_text_field( $_POST['oc_pyembroidery_cli_path'] ?? '' ),
			'embroidery_fallback'    => in_array( $_POST['oc_embroidery_fallback'] ?? '', [ 'auto', 'force_tier2' ], true )
				? sanitize_key( $_POST['oc_embroidery_fallback'] )
				: 'auto',
			'bleed_mm'               => max( 0, (float) ( $_POST['oc_bleed_mm'] ?? 3 ) ),
			'crop_mark_style'        => in_array( $_POST['oc_crop_mark_style'] ?? '', [ 'standard', 'none' ], true )
				? sanitize_key( $_POST['oc_crop_mark_style'] )
				: 'standard',
			'icc_engraving'          => sanitize_text_field( $_POST['oc_icc_engraving'] ?? '' ),
			'icc_uv'                 => sanitize_text_field( $_POST['oc_icc_uv'] ?? '' ),
			'icc_sublimation'        => sanitize_text_field( $_POST['oc_icc_sublimation'] ?? '' ),
		];

		update_option( self::OPTION_KEY, $settings );
		add_settings_error( 'oc_settings', 'saved', __( 'Settings saved.', 'overcustomise' ), 'success' );
	}
}
