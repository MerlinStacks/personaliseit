<?php
/**
 * Settings page - file retention, upload limits, embroidery paths, print defaults.
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
			'embroidery_fallback'    => 'auto', // 'auto' | 'force_tier2'

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

		$tabs = [
			'general'    => __( 'General', 'overcustomise' ),
			'files'      => __( 'File Management', 'overcustomise' ),
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
								<p class="oc-form-help"><?php esc_html_e( 'Use tab links with #general, #files, #embroidery, or #print to open a section directly.', 'overcustomise' ); ?></p>
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
												<label for="oc_file_retention_days"><?php esc_html_e( 'Print file retention', 'overcustomise' ); ?></label>
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
													$all_formats = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg' ];
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
							id="oc-settings-panel-embroidery"
							class="oc-settings-tab-panel<?php echo $active_tab === 'embroidery' ? ' is-active' : ''; ?>"
							data-tab-panel="embroidery"
							role="tabpanel">
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
												<input
													type="text"
													id="oc_python_binary"
													name="oc_python_binary"
													value="<?php echo esc_attr( $s['python_binary'] ); ?>"
													class="regular-text oc-input"
													placeholder="python3" />
												<p class="oc-form-help"><?php esc_html_e( 'Full path or command name (e.g. python3 or /usr/bin/python3).', 'overcustomise' ); ?></p>
											</div>
										</div>
										<div class="oc-form-row">
											<div class="oc-form-label">
												<label for="oc_pyembroidery_cli_path"><?php esc_html_e( 'pyembroidery-CLI path', 'overcustomise' ); ?></label>
											</div>
											<div class="oc-form-field">
												<input
													type="text"
													id="oc_pyembroidery_cli_path"
													name="oc_pyembroidery_cli_path"
													value="<?php echo esc_attr( $s['pyembroidery_cli_path'] ); ?>"
													class="regular-text oc-input"
													placeholder="/path/to/pyemb_convert.py" />
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
												<label for="oc_bleed_mm"><?php esc_html_e( 'Bleed size', 'overcustomise' ); ?></label>
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
												<?php esc_html_e( 'ICC profiles', 'overcustomise' ); ?>
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

	/** Show Python availability status notice. */
	private function render_python_status(): void {
		$python = OC_Admin_Settings::get( 'python_binary' ) ?: 'python3';
		$cli    = OC_Admin_Settings::get( 'pyembroidery_cli_path' );
		$py_ok  = $this->check_python( $python );
		$cli_ok = $cli && file_exists( $cli );

		if ( $py_ok && $cli_ok ) {
			$msg   = __( 'Tier 1 auto-DST is available (Python found, pyembroidery-CLI path set).', 'overcustomise' );
			$class = 'notice-success';
		} elseif ( $py_ok ) {
			$msg   = __( 'Python found but pyembroidery-CLI path is not set - Tier 2 (production brief) will be used.', 'overcustomise' );
			$class = 'notice-warning';
		} else {
			$msg   = __( 'Python not found - Tier 2 (production brief + manual DST upload) will be used for embroidery.', 'overcustomise' );
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
		try {
			$result = OC_Command_Runner::run( [ $binary, '--version' ] );
			return 0 === (int) $result['code'];
		} catch ( \InvalidArgumentException $e ) {
			return false;
		}
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
