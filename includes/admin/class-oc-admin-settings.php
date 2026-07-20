<?php
/**
 * Settings page - file retention, upload limits, embroidery paths, print defaults.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Settings {

	private const OPTION_KEY = 'oc_settings';
	private const OPENROUTER_MODELS_TRANSIENT = 'oc_openrouter_image_models';

	private const OPENROUTER_IMAGE_MODELS = [
		'google/gemini-2.5-flash-image-preview' => 'Google Gemini 2.5 Flash Image Preview',
		'google/gemini-2.0-flash-exp'           => 'Google Gemini 2.0 Flash Experimental',
		'openai/gpt-image-1'                    => 'OpenAI GPT Image 1',
	];

	/** Return the full settings array with defaults applied. */
	public static function get( string $key = '' ): mixed {
		$defaults = [
			// General.
			'flat_rate_default'      => '0.00',

			// File management.
			'file_retention_days'    => 90,
			'max_upload_size_mb'     => 10,
			'allowed_upload_formats' => [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg', 'webp' ],

			// Print defaults.
			'bleed_mm'               => 3,
			'crop_mark_style'        => 'standard', // 'standard' | 'none'
			'icc_engraving'          => 'GrayGamma2.2',
			'icc_uv'                 => 'ISOcoated_v2_300_eci',
			'icc_sublimation'        => 'ISOcoated_v2_300_eci',

			// AI image filter generation.
			'openrouter_api_key_enc' => '',
			'openrouter_image_model' => 'google/gemini-2.5-flash-image-preview',
		];

		$saved = get_option( self::OPTION_KEY, [] );
		$all   = wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
		$formats = is_array( $all['allowed_upload_formats'] ?? null ) ? $all['allowed_upload_formats'] : [];
		$formats = array_values( array_unique( array_intersect(
			[ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg', 'webp' ],
			array_map( static fn ( $format ): string => is_scalar( $format ) ? sanitize_key( (string) $format ) : '', $formats )
		) ) );
		$flat_rate = is_numeric( $all['flat_rate_default'] ?? null ) ? (float) $all['flat_rate_default'] : 0.0;
		$bleed     = is_numeric( $all['bleed_mm'] ?? null ) ? (float) $all['bleed_mm'] : 3.0;
		$all = [
			'flat_rate_default'      => number_format( max( 0, min( 1000000, is_finite( $flat_rate ) ? $flat_rate : 0.0 ) ), 2, '.', '' ),
			'file_retention_days'    => max( 1, min( 3650, is_numeric( $all['file_retention_days'] ?? null ) ? (int) $all['file_retention_days'] : 90 ) ),
			'max_upload_size_mb'     => max( 1, min( 100, is_numeric( $all['max_upload_size_mb'] ?? null ) ? (int) $all['max_upload_size_mb'] : 10 ) ),
			'allowed_upload_formats' => $formats,
			'bleed_mm'               => max( 0, min( 100, is_finite( $bleed ) ? $bleed : 3.0 ) ),
			'crop_mark_style'        => in_array( $all['crop_mark_style'] ?? null, [ 'standard', 'none' ], true ) ? $all['crop_mark_style'] : 'standard',
			'icc_engraving'          => is_scalar( $all['icc_engraving'] ?? null ) ? substr( sanitize_text_field( (string) $all['icc_engraving'] ), 0, 255 ) : $defaults['icc_engraving'],
			'icc_uv'                 => is_scalar( $all['icc_uv'] ?? null ) ? substr( sanitize_text_field( (string) $all['icc_uv'] ), 0, 255 ) : $defaults['icc_uv'],
			'icc_sublimation'        => is_scalar( $all['icc_sublimation'] ?? null ) ? substr( sanitize_text_field( (string) $all['icc_sublimation'] ), 0, 255 ) : $defaults['icc_sublimation'],
			'openrouter_api_key_enc' => is_string( $all['openrouter_api_key_enc'] ?? null ) && strlen( $all['openrouter_api_key_enc'] ) <= 4096 ? $all['openrouter_api_key_enc'] : '',
			'openrouter_image_model' => is_scalar( $all['openrouter_image_model'] ?? null ) ? substr( sanitize_text_field( (string) $all['openrouter_image_model'] ), 0, 255 ) : $defaults['openrouter_image_model'],
		];

		if ( '' !== $key ) {
			return $all[ $key ] ?? null;
		}

		return $all;
	}

	/** Return the decrypted OpenRouter API key for server-side API calls. */
	public static function get_openrouter_api_key(): string {
		$encrypted = (string) self::get( 'openrouter_api_key_enc' );
		return self::decrypt_secret( $encrypted );
	}

	/** Return the configured OpenRouter image model. */
	public static function get_openrouter_image_model(): string {
		$model = (string) self::get( 'openrouter_image_model' );
		$models = self::get_openrouter_image_models();
		return isset( $models[ $model ] ) ? $model : 'google/gemini-2.5-flash-image-preview';
	}

	/** Return image-capable OpenRouter models, cached with a safe built-in fallback. */
	public static function get_openrouter_image_models( bool $force_refresh = false ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::OPENROUTER_MODELS_TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$models = self::fetch_openrouter_image_models();
		if ( empty( $models ) ) {
			return self::OPENROUTER_IMAGE_MODELS;
		}

		set_transient( self::OPENROUTER_MODELS_TRANSIENT, $models, 6 * HOUR_IN_SECONDS );
		return $models;
	}

	private static function fetch_openrouter_image_models(): array {
		$headers = [
			'Accept'       => 'application/json',
			'HTTP-Referer' => home_url(),
			'X-Title'      => 'OverCustomise',
		];
		$api_key = self::get_openrouter_api_key();
		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_get(
			'https://openrouter.ai/api/v1/models',
			[
				'timeout' => 15,
				'headers' => $headers,
			]
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body['data'] ?? null ) ) {
			return [];
		}

		$models = [];
		foreach ( $body['data'] as $model ) {
			if ( ! is_array( $model ) || empty( $model['id'] ) ) {
				continue;
			}
			$architecture = is_array( $model['architecture'] ?? null ) ? $model['architecture'] : [];
			$outputs      = is_array( $architecture['output_modalities'] ?? null ) ? array_map( 'strtolower', $architecture['output_modalities'] ) : [];
			$modality     = strtolower( (string) ( $architecture['modality'] ?? '' ) );
			$model_id     = sanitize_text_field( (string) $model['id'] );

			if ( ! in_array( 'image', $outputs, true ) && ! str_contains( $modality, 'image' ) && ! str_contains( strtolower( $model_id ), 'image' ) ) {
				continue;
			}

			$models[ $model_id ] = sanitize_text_field( (string) ( $model['name'] ?? $model_id ) );
		}

		asort( $models, SORT_NATURAL | SORT_FLAG_CASE );
		return $models;
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
		$image_models = self::get_openrouter_image_models();

		$tabs = [
			'general'    => __( 'General', 'overcustomise' ),
			'files'      => __( 'File Management', 'overcustomise' ),
			'ai'         => __( 'AI Image Filters', 'overcustomise' ),
			'embroidery' => __( 'Embroidery', 'overcustomise' ),
			'print'      => __( 'Print Defaults', 'overcustomise' ),
		];

		$active_tab = 'general';
		if ( isset( $_POST['oc_active_tab'] ) ) {
			$posted_tab = sanitize_key( wp_unslash( $_POST['oc_active_tab'] ) );
			if ( isset( $tabs[ $posted_tab ] ) ) {
				$active_tab = $posted_tab;
			}
		} elseif ( isset( $_GET['tab'] ) ) {
			$query_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			if ( isset( $tabs[ $query_tab ] ) ) {
				$active_tab = $query_tab;
			}
		}
		?>
		<div class="wrap oc-page oc-settings-page">
			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Settings', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Configure pricing defaults, customer uploads, embroidery tooling, and print output standards.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<?php settings_errors( 'oc_settings' ); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'oc_save_settings', 'oc_settings_nonce' ); ?>
				<input type="hidden" name="oc_active_tab" id="oc_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

				<div class="oc-settings-layout" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
					<aside class="oc-settings-sidebar">
						<div class="oc-card">
							<div class="oc-card-header">
								<h2><?php esc_html_e( 'Sections', 'overcustomise' ); ?></h2>
							</div>
							<div class="oc-card-body">
								<div class="oc-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'overcustomise' ); ?>">
									<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
										<button
											type="button"
											class="oc-settings-tab-btn<?php echo $active_tab === $tab_key ? ' is-active' : ''; ?>"
											data-tab="<?php echo esc_attr( $tab_key ); ?>"
											role="tab"
											aria-selected="<?php echo $active_tab === $tab_key ? 'true' : 'false'; ?>"
											aria-controls="<?php echo esc_attr( 'oc-settings-panel-' . $tab_key ); ?>">
											<span class="oc-settings-tab-title"><?php echo esc_html( $tab_label ); ?></span>
										</button>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="oc-card oc-settings-help-card">
							<div class="oc-card-header">
								<h2><?php esc_html_e( 'Quick Tips', 'overcustomise' ); ?></h2>
							</div>
							<div class="oc-card-body">
								<p class="oc-form-help"><?php esc_html_e( 'Use tab links with #general, #files, #ai, #embroidery, or #print to open a section directly.', 'overcustomise' ); ?></p>
								<p class="oc-form-help"><?php esc_html_e( 'Your last open tab is preserved when saving.', 'overcustomise' ); ?></p>
							</div>
						</div>
					</aside>

					<div class="oc-settings-content">
						<div
							id="oc-settings-panel-general"
							class="oc-settings-tab-panel<?php echo $active_tab === 'general' ? ' is-active' : ''; ?>"
							data-tab-panel="general"
							role="tabpanel">
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
												<div class="oc-inline-row">
													<input
														type="number"
														id="oc_flat_rate_default"
														name="oc_flat_rate_default"
														value="<?php echo esc_attr( $s['flat_rate_default'] ); ?>"
														min="0"
														step="0.01"
														class="small-text oc-input oc-input-small" />
												</div>
												<p class="oc-form-help"><?php esc_html_e( 'Applied per item unless overridden on the product config.', 'overcustomise' ); ?></p>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div
							id="oc-settings-panel-files"
							class="oc-settings-tab-panel<?php echo $active_tab === 'files' ? ' is-active' : ''; ?>"
							data-tab-panel="files"
							role="tabpanel">
							<div class="oc-card">
								<div class="oc-card-header">
									<h2><?php esc_html_e( 'File Management', 'overcustomise' ); ?></h2>
								</div>
								<div class="oc-card-body">
									<div class="oc-form-grid">
										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_file_retention_days"><?php esc_html_e( 'Print file retention', 'overcustomise' ); ?><?php OC_Tooltips::render( 'file-retention', __( 'How long generated print files are kept before automatic deletion.', 'overcustomise' ) ); ?></label>
											</div>
											<div class="oc-form-field">
												<div class="oc-inline-row">
													<input
														type="number"
														id="oc_file_retention_days"
														name="oc_file_retention_days"
														value="<?php echo esc_attr( $s['file_retention_days'] ); ?>"
														min="1"
														step="1"
														class="small-text oc-input oc-input-xs" />
													<span class="oc-input-suffix"><?php esc_html_e( 'days', 'overcustomise' ); ?></span>
												</div>
												<p class="oc-form-help"><?php esc_html_e( 'Generated print files are automatically deleted after this many days. Default: 90.', 'overcustomise' ); ?></p>
											</div>
										</div>
										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_max_upload_size_mb"><?php esc_html_e( 'Max customer upload size', 'overcustomise' ); ?></label>
											</div>
											<div class="oc-form-field">
												<div class="oc-inline-row">
													<input
														type="number"
														id="oc_max_upload_size_mb"
														name="oc_max_upload_size_mb"
														value="<?php echo esc_attr( $s['max_upload_size_mb'] ); ?>"
														min="1"
														step="1"
														class="small-text oc-input oc-input-xs" />
													<span class="oc-input-suffix"><?php esc_html_e( 'MB', 'overcustomise' ); ?></span>
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
											$all_formats = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg', 'webp' ];
											foreach ( $all_formats as $fmt ) :
														$checked = in_array( $fmt, (array) $s['allowed_upload_formats'], true );
														?>
														<label class="oc-checkbox-label">
															<input
																type="checkbox"
																name="oc_allowed_upload_formats[]"
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
						</div>

						<div
							id="oc-settings-panel-ai"
							class="oc-settings-tab-panel<?php echo $active_tab === 'ai' ? ' is-active' : ''; ?>"
							data-tab-panel="ai"
							role="tabpanel">
							<div class="oc-card">
								<div class="oc-card-header">
									<h2><?php esc_html_e( 'AI Image Filters', 'overcustomise' ); ?></h2>
								</div>
								<div class="oc-card-body">
									<div class="oc-form-grid">
										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_openrouter_api_key"><?php esc_html_e( 'OpenRouter API key', 'overcustomise' ); ?></label>
											</div>
											<div class="oc-form-field">
												<input
													type="password"
													id="oc_openrouter_api_key"
													name="oc_openrouter_api_key"
													value=""
													class="regular-text oc-input"
													autocomplete="new-password"
													placeholder="<?php echo '' !== (string) $s['openrouter_api_key_enc'] ? esc_attr__( 'API key saved. Enter a new key to replace it.', 'overcustomise' ) : esc_attr__( 'sk-or-v1-...', 'overcustomise' ); ?>" />
												<p class="oc-form-help"><?php esc_html_e( 'Stored encrypted in the WordPress options table and only used server-side.', 'overcustomise' ); ?></p>
												<?php if ( '' !== (string) $s['openrouter_api_key_enc'] ) : ?>
													<label class="oc-checkbox-label" style="margin-top:8px;">
														<input type="checkbox" name="oc_openrouter_api_key_clear" value="1" />
														<?php esc_html_e( 'Clear saved API key', 'overcustomise' ); ?>
													</label>
												<?php endif; ?>
											</div>
										</div>

										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_openrouter_image_model"><?php esc_html_e( 'Image model', 'overcustomise' ); ?></label>
											</div>
											<div class="oc-form-field">
												<select id="oc_openrouter_image_model" name="oc_openrouter_image_model" class="oc-select oc-select-wide">
													<?php foreach ( $image_models as $model_id => $model_label ) : ?>
														<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $s['openrouter_image_model'], $model_id ); ?>>
															<?php echo esc_html( $model_label ); ?>
														</option>
													<?php endforeach; ?>
												</select>
												<p class="oc-form-help"><?php esc_html_e( 'Fetched from OpenRouter and cached for 6 hours. Falls back to known image models if OpenRouter is unavailable.', 'overcustomise' ); ?></p>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div
							id="oc-settings-panel-embroidery"
							class="oc-settings-tab-panel<?php echo $active_tab === 'embroidery' ? ' is-active' : ''; ?>"
							data-tab-panel="embroidery"
							role="tabpanel">
							<div class="oc-card">
								<div class="oc-card-header">
									<h2><?php esc_html_e( 'Embroidery', 'overcustomise' ); ?></h2>
								</div>
								<div class="oc-card-body">
									<div class="notice notice-info inline"><p><?php esc_html_e( 'Embroidery print files are generated directly as EPS artwork files. No stitch-file tooling setup is required.', 'overcustomise' ); ?></p></div>
									<p class="oc-form-help"><?php esc_html_e( 'Set the print area to the real embroidery size and keep artwork/text inside the configured bounds so the imported EPS matches production scale.', 'overcustomise' ); ?></p>
								</div>
							</div>
						</div>

						<div
							id="oc-settings-panel-print"
							class="oc-settings-tab-panel<?php echo $active_tab === 'print' ? ' is-active' : ''; ?>"
							data-tab-panel="print"
							role="tabpanel">
							<div class="oc-card">
								<div class="oc-card-header">
									<h2><?php esc_html_e( 'Print Defaults', 'overcustomise' ); ?></h2>
								</div>
								<div class="oc-card-body">
									<div class="oc-form-grid">
										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_bleed_mm"><?php esc_html_e( 'Bleed size', 'overcustomise' ); ?><?php OC_Tooltips::render( 'bleed-size', __( 'Extra artwork area beyond the trim edge, in millimetres.', 'overcustomise' ) ); ?></label>
											</div>
											<div class="oc-form-field">
												<div class="oc-inline-row">
													<input
														type="number"
														id="oc_bleed_mm"
														name="oc_bleed_mm"
														value="<?php echo esc_attr( $s['bleed_mm'] ); ?>"
														min="0"
														step="0.5"
														class="small-text oc-input oc-input-xs" />
													<span class="oc-input-suffix">mm</span>
												</div>
											</div>
										</div>
										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_crop_mark_style"><?php esc_html_e( 'Crop mark style', 'overcustomise' ); ?></label>
											</div>
											<div class="oc-form-field">
												<select id="oc_crop_mark_style" name="oc_crop_mark_style" class="oc-select oc-select-wide">
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
												<?php esc_html_e( 'ICC profiles', 'overcustomise' ); ?><?php OC_Tooltips::render( 'icc-profiles', __( 'ICC colour profile names or paths embedded in generated PDFs for each print method.', 'overcustomise' ) ); ?>
											</div>
											<div class="oc-form-field">
												<div class="oc-icc-grid">
													<div class="oc-inline-row">
														<span class="oc-icc-label"><?php esc_html_e( 'Engraving', 'overcustomise' ); ?></span>
														<input type="text" name="oc_icc_engraving" value="<?php echo esc_attr( $s['icc_engraving'] ); ?>" class="regular-text oc-input" />
													</div>
													<div class="oc-inline-row">
														<span class="oc-icc-label"><?php esc_html_e( 'UV Printing', 'overcustomise' ); ?></span>
														<input type="text" name="oc_icc_uv" value="<?php echo esc_attr( $s['icc_uv'] ); ?>" class="regular-text oc-input" />
													</div>
													<div class="oc-inline-row">
														<span class="oc-icc-label"><?php esc_html_e( 'Sublimation', 'overcustomise' ); ?></span>
														<input type="text" name="oc_icc_sublimation" value="<?php echo esc_attr( $s['icc_sublimation'] ); ?>" class="regular-text oc-input" />
													</div>
												</div>
												<p class="oc-form-help"><?php esc_html_e( 'ICC profile name or path embedded in generated PDFs.', 'overcustomise' ); ?></p>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="oc-card">
							<div class="oc-card-footer oc-settings-savebar">
								<button type="submit" class="oc-btn oc-btn-primary">
									<?php esc_html_e( 'Save Settings', 'overcustomise' ); ?>
								</button>
								<div class="oc-settings-save-meta">
									<span id="oc-settings-dirty-indicator" class="oc-settings-dirty-indicator" aria-live="polite">
										<?php esc_html_e( 'Unsaved changes', 'overcustomise' ); ?>
									</span>
									<span class="oc-form-help"><?php esc_html_e( 'Changes apply globally to product customisation workflows.', 'overcustomise' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
		$this->render_tabs_script( $tabs, $active_tab );
	}

	/**
	 * Tab switcher script for settings page.
	 *
	 * @param array<string, string> $tabs Available tabs.
	 * @param string                $active_tab Server-side active tab key.
	 */
	private function render_tabs_script( array $tabs, string $active_tab ): void {
		$allowed_tabs = wp_json_encode( array_keys( $tabs ) );
		$safe_tab     = wp_json_encode( $active_tab );
		?>
		<script>
		(function () {
			const allowedTabs = <?php echo $allowed_tabs; ?> || [];
			const fallbackTab = <?php echo $safe_tab; ?> || "general";

			const layout = document.querySelector(".oc-settings-layout");
			if (!layout) {
				return;
			}

			const input = document.getElementById("oc_active_tab");
			const buttons = Array.from(document.querySelectorAll(".oc-settings-tab-btn[data-tab]"));
			const panels = Array.from(document.querySelectorAll(".oc-settings-tab-panel[data-tab-panel]"));

			const getHashTab = () => {
				const hash = window.location.hash.replace("#", "");
				return allowedTabs.includes(hash) ? hash : "";
			};

			const setActive = (tab, shouldPushHash) => {
				if (!allowedTabs.includes(tab)) {
					tab = fallbackTab;
				}

				layout.dataset.activeTab = tab;
				if (input) {
					input.value = tab;
				}

				buttons.forEach((button) => {
					const isActive = button.dataset.tab === tab;
					button.classList.toggle("is-active", isActive);
					button.setAttribute("aria-selected", isActive ? "true" : "false");
				});

				panels.forEach((panel) => {
					const isActive = panel.dataset.tabPanel === tab;
					panel.classList.toggle("is-active", isActive);
					panel.hidden = !isActive;
				});

				if (shouldPushHash) {
					window.history.replaceState(null, "", "#" + tab);
				}
			};

			buttons.forEach((button) => {
				button.addEventListener("click", () => {
					setActive(button.dataset.tab, true);
				});

				button.addEventListener("keydown", (event) => {
					const currentIndex = buttons.indexOf(button);
					if (currentIndex === -1) {
						return;
					}

					let nextIndex = -1;
					if (event.key === "ArrowDown" || event.key === "ArrowRight") {
						nextIndex = (currentIndex + 1) % buttons.length;
					}
					if (event.key === "ArrowUp" || event.key === "ArrowLeft") {
						nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
					}
					if (event.key === "Home") {
						nextIndex = 0;
					}
					if (event.key === "End") {
						nextIndex = buttons.length - 1;
					}

					if (nextIndex !== -1) {
						event.preventDefault();
						const nextButton = buttons[nextIndex];
						nextButton.focus();
						setActive(nextButton.dataset.tab, true);
					}
				});
			});

			setActive(getHashTab() || fallbackTab, false);
			window.addEventListener("hashchange", () => {
				const nextTab = getHashTab();
				if (nextTab) {
					setActive(nextTab, false);
				}
			});

			const form = document.querySelector(".oc-settings-page form");
			const dirtyIndicator = document.getElementById("oc-settings-dirty-indicator");
			let isDirty = false;

			const setDirty = (value) => {
				isDirty = value;
				if (!dirtyIndicator) {
					return;
				}
				dirtyIndicator.classList.toggle("is-visible", value);
			};

			if (form) {
				form.addEventListener("input", (event) => {
					if (!event.target || event.target.id === "oc_active_tab") {
						return;
					}
					setDirty(true);
				});

				form.addEventListener("change", (event) => {
					if (!event.target || event.target.id === "oc_active_tab") {
						return;
					}
					setDirty(true);
				});

				form.addEventListener("submit", () => {
					setDirty(false);
				});
			}

			window.addEventListener("beforeunload", (event) => {
				if (!isDirty) {
					return;
				}
				event.preventDefault();
				event.returnValue = "";
			});
		})();
		</script>
		<?php
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

		$allowed_formats = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg', 'webp' ];
		$posted_formats  = isset( $_POST['oc_allowed_upload_formats'] )
			? array_intersect( (array) $_POST['oc_allowed_upload_formats'], $allowed_formats )
			: [];
		$current_settings = self::get();
		$api_key_encrypted = (string) ( $current_settings['openrouter_api_key_enc'] ?? '' );
		$posted_api_key = isset( $_POST['oc_openrouter_api_key'] ) ? trim( (string) wp_unslash( $_POST['oc_openrouter_api_key'] ) ) : '';
		if ( ! empty( $_POST['oc_openrouter_api_key_clear'] ) ) {
			$api_key_encrypted = '';
		} elseif ( '' !== $posted_api_key ) {
			$api_key_encrypted = self::encrypt_secret( $posted_api_key );
		}

		$model = sanitize_text_field( wp_unslash( $_POST['oc_openrouter_image_model'] ?? '' ) );
		$image_models = self::get_openrouter_image_models();
		if ( ! isset( $image_models[ $model ] ) ) {
			$model = 'google/gemini-2.5-flash-image-preview';
		}

		$flat_rate = is_numeric( $_POST['oc_flat_rate_default'] ?? null ) ? (float) $_POST['oc_flat_rate_default'] : 0.0;
		$bleed     = is_numeric( $_POST['oc_bleed_mm'] ?? null ) ? (float) $_POST['oc_bleed_mm'] : 3.0;
		$settings = [
			'flat_rate_default'      => number_format( max( 0, min( 1000000, is_finite( $flat_rate ) ? $flat_rate : 0.0 ) ), 2, '.', '' ),
			'file_retention_days'    => max( 1, min( 3650, (int) ( $_POST['oc_file_retention_days'] ?? 90 ) ) ),
			'max_upload_size_mb'     => max( 1, min( 100, (int) ( $_POST['oc_max_upload_size_mb'] ?? 10 ) ) ),
			'allowed_upload_formats' => array_values( $posted_formats ),
			'bleed_mm'               => max( 0, min( 100, is_finite( $bleed ) ? $bleed : 3.0 ) ),
			'crop_mark_style'        => in_array( $_POST['oc_crop_mark_style'] ?? '', [ 'standard', 'none' ], true )
				? sanitize_key( $_POST['oc_crop_mark_style'] )
				: 'standard',
			'icc_engraving'          => substr( sanitize_text_field( wp_unslash( $_POST['oc_icc_engraving'] ?? '' ) ), 0, 255 ),
			'icc_uv'                 => substr( sanitize_text_field( wp_unslash( $_POST['oc_icc_uv'] ?? '' ) ), 0, 255 ),
			'icc_sublimation'        => substr( sanitize_text_field( wp_unslash( $_POST['oc_icc_sublimation'] ?? '' ) ), 0, 255 ),
			'openrouter_api_key_enc' => $api_key_encrypted,
			'openrouter_image_model' => $model,
		];

		update_option( self::OPTION_KEY, $settings );
		if ( '' !== $posted_api_key || ! empty( $_POST['oc_openrouter_api_key_clear'] ) ) {
			delete_transient( self::OPENROUTER_MODELS_TRANSIENT );
		}
		add_settings_error( 'oc_settings', 'saved', __( 'Settings saved.', 'overcustomise' ), 'success' );
	}

	private static function encrypt_secret( string $secret ): string {
		if ( '' === $secret || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		$iv = random_bytes( 16 );
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$ciphertext = openssl_encrypt( $secret, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return '';
		}

		return 'v1:' . base64_encode( $iv . $ciphertext );
	}

	private static function decrypt_secret( string $encrypted ): string {
		if ( '' === $encrypted || ! str_starts_with( $encrypted, 'v1:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $encrypted, 3 ), true );
		if ( ! is_string( $raw ) || strlen( $raw ) <= 16 ) {
			return '';
		}

		$iv = substr( $raw, 0, 16 );
		$ciphertext = substr( $raw, 16 );
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$plain = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return is_string( $plain ) ? $plain : '';
	}
}
