<?php
/**
 * AI image filter manager page.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Image_Filters {

	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_test_ai_image_filter', [ self::class, 'ajax_test_filter' ] );
	}

	/** Run a prompt against an admin-supplied test image. */
	public static function ajax_test_filter(): void {
		check_ajax_referer( 'oc-image-filter-test', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ], 403 );
		}

		$prompt = isset( $_POST['prompt'] ) ? trim( (string) wp_unslash( $_POST['prompt'] ) ) : '';
		$remove_background = ! empty( $_POST['remove_background'] );
		if ( '' === $prompt || strlen( $prompt ) > 10000 || empty( $_FILES['test_image'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Enter a prompt and choose a test image.', 'overcustomise' ) ], 400 );
		}

		$source_id    = 0;
		$generated_id = 0;
		$response     = null;
		$error        = null;
		$status       = 422;
		try {
			$uploaded  = OC_Upload_Handler::process( $_FILES['test_image'], [ 'formats' => [ 'jpg', 'jpeg', 'png', 'webp' ] ] );
			$source_id = (int) $uploaded['attachment_id'];
			$result    = OC_AI_Image_Filter::generate( $source_id, $prompt );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				if ( $remove_background ) {
					$saved = OC_Upload_Handler::save_generated_image( $result['bytes'], $result['mime'], [], [], true );
					if ( is_wp_error( $saved ) ) {
						throw new \RuntimeException( $saved->get_error_message() );
					}
					$generated_id = (int) $saved['attachment_id'];
					$path         = get_attached_file( $generated_id );
					$bytes        = is_string( $path ) ? file_get_contents( $path ) : false;
					$mime         = (string) get_post_mime_type( $generated_id );
					if ( ! is_string( $bytes ) || '' === $bytes || ! in_array( $mime, [ 'image/png', 'image/jpeg', 'image/webp' ], true ) ) {
						throw new \RuntimeException( __( 'The background-removed test image could not be read.', 'overcustomise' ) );
					}
					$result['bytes'] = $bytes;
					$result['mime']  = $mime;
				}
				$response = [
					'image' => 'data:' . $result['mime'] . ';base64,' . base64_encode( $result['bytes'] ),
					'model' => $result['model'],
				];
			}
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		} finally {
			if ( $generated_id ) {
				wp_delete_attachment( $generated_id, true );
			}
			if ( $source_id ) {
				wp_delete_attachment( $source_id, true );
			}
		}

		if ( null !== $error || ! is_array( $response ) ) {
			wp_send_json_error( [ 'message' => $error ?: __( 'Test failed.', 'overcustomise' ) ], $status );
		}
		wp_send_json_success( $response );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'delete' === $action ) {
			$this->handle_delete();
		} elseif ( 'toggle' === $action ) {
			$this->handle_toggle();
		}

		if ( isset( $_POST['oc_image_filter_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oc_image_filter_nonce'] ) ), 'oc_image_filter_save' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
			}
			$saved = $this->handle_save();
			wp_safe_redirect( add_query_arg(
				'oc_filter_notice',
				$saved ? 'saved' : 'error',
				admin_url( 'admin.php?page=overcustomise-image-filters' )
			) );
			exit;
		}

		$edit_id = 'edit' === $action ? absint( $_GET['id'] ?? 0 ) : 0;
		$editing = $edit_id ? $this->get_filter( $edit_id ) : null;
		$filters = OC_DB::get_image_filters( false );
		?>
		<div class="wrap oc-page">
			<?php if ( 'saved' === sanitize_key( wp_unslash( $_GET['oc_filter_notice'] ?? '' ) ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Image filter saved.', 'overcustomise' ); ?></p></div>
			<?php elseif ( 'error' === sanitize_key( wp_unslash( $_GET['oc_filter_notice'] ?? '' ) ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not save the image filter.', 'overcustomise' ); ?></p></div>
			<?php endif; ?>
			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'AI Image Filters', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Create reusable OpenRouter prompts, test them, then enable them on image layers in a design.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<?php if ( '' === OC_Admin_Settings::get_openrouter_api_key() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Add an OpenRouter API key in Settings > AI Image Filters before testing or using AI filters.', 'overcustomise' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! extension_loaded( 'imagick' ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Enable the PHP ImageMagick extension to use AI filter background removal.', 'overcustomise' ); ?></p></div>
			<?php endif; ?>

			<div class="oc-card" style="margin-bottom:18px;">
				<div class="oc-card-header"><h2><?php echo $editing ? esc_html__( 'Edit Filter', 'overcustomise' ) : esc_html__( 'Add Filter', 'overcustomise' ); ?></h2></div>
				<form method="post" id="oc-ai-filter-form" style="padding:18px;">
					<?php wp_nonce_field( 'oc_image_filter_save', 'oc_image_filter_nonce' ); ?>
					<input type="hidden" name="filter_id" value="<?php echo esc_attr( $edit_id ); ?>" />
					<div class="oc-form-grid">
						<div class="oc-form-row">
							<div class="oc-form-label"><label for="oc_filter_name"><?php esc_html_e( 'Filter name', 'overcustomise' ); ?></label></div>
							<div class="oc-form-field"><input type="text" id="oc_filter_name" name="name" class="regular-text oc-input" required value="<?php echo esc_attr( (string) ( $editing->name ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Embroidery pet outline', 'overcustomise' ); ?>" /></div>
						</div>
						<div class="oc-form-row">
							<div class="oc-form-label"><label for="oc_filter_prompt"><?php esc_html_e( 'AI prompt', 'overcustomise' ); ?></label></div>
							<div class="oc-form-field"><textarea id="oc_filter_prompt" name="prompt" class="large-text code" rows="18" required placeholder="<?php esc_attr_e( 'Describe exactly how the uploaded image should be transformed...', 'overcustomise' ); ?>"><?php echo esc_textarea( (string) ( $editing->prompt ?? '' ) ); ?></textarea><p class="oc-form-help"><?php esc_html_e( 'The customer image is sent to the globally selected OpenRouter image model with this prompt.', 'overcustomise' ); ?></p></div>
						</div>
						<div class="oc-form-row">
							<div class="oc-form-label"><label for="oc_filter_remove_background"><?php esc_html_e( 'Remove background', 'overcustomise' ); ?></label></div>
							<div class="oc-form-field"><label><input type="checkbox" id="oc_filter_remove_background" name="remove_background" value="1" <?php checked( ! empty( $editing->remove_background ) ); ?> /> <?php esc_html_e( 'Remove the generated image background', 'overcustomise' ); ?></label><p class="oc-form-help"><?php esc_html_e( 'Uses low-tolerance, edge-connected ImageMagick processing after the AI filter. Best results require a plain, consistent background.', 'overcustomise' ); ?></p></div>
						</div>
						<div class="oc-form-row">
							<div class="oc-form-label"><label for="oc_filter_test_image"><?php esc_html_e( 'Test image', 'overcustomise' ); ?></label></div>
							<div class="oc-form-field"><input type="file" id="oc_filter_test_image" accept="image/jpeg,image/png,image/webp" /><button type="button" id="oc-test-ai-filter" class="button" style="margin-left:8px;"><?php esc_html_e( 'Run Test', 'overcustomise' ); ?></button><span id="oc-ai-test-status" style="margin-left:8px;"></span><div id="oc-ai-test-result" style="display:none;margin-top:12px;"><img alt="<?php esc_attr_e( 'AI filter test result', 'overcustomise' ); ?>" style="max-width:520px;max-height:520px;border:1px solid #dcdcde;background:#fff;" /></div></div>
						</div>
					</div>
					<div style="display:flex;gap:8px;margin-top:16px;"><button type="submit" class="oc-btn oc-btn-primary"><?php echo $editing ? esc_html__( 'Save Filter', 'overcustomise' ) : esc_html__( 'Add Filter', 'overcustomise' ); ?></button><?php if ( $editing ) : ?><a class="oc-btn oc-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-image-filters' ) ); ?>"><?php esc_html_e( 'Cancel', 'overcustomise' ); ?></a><?php endif; ?></div>
				</form>
			</div>

			<div class="oc-card">
				<div class="oc-card-header"><h2><?php esc_html_e( 'Filters', 'overcustomise' ); ?></h2><span style="font-size:12px;color:var(--oc-gray-400);"><?php echo esc_html( count( $filters ) ); ?></span></div>
				<?php if ( empty( $filters ) ) : ?>
					<div class="oc-empty"><h3><?php esc_html_e( 'No filters yet', 'overcustomise' ); ?></h3><p><?php esc_html_e( 'Add a prompt above, then enable it on image layers in the design editor.', 'overcustomise' ); ?></p></div>
				<?php else : ?>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Type', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Status', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Actions', 'overcustomise' ); ?></th></tr></thead><tbody>
					<?php foreach ( $filters as $filter ) : ?><tr><td><strong><?php echo esc_html( $filter->name ); ?></strong><?php if ( ! empty( $filter->prompt ) ) : ?><details><summary><?php esc_html_e( 'View prompt', 'overcustomise' ); ?></summary><pre style="white-space:pre-wrap;max-width:700px;"><?php echo esc_html( $filter->prompt ); ?></pre></details><?php endif; ?></td><td><?php echo 'ai' === $filter->filter_key ? esc_html__( 'AI prompt', 'overcustomise' ) : esc_html__( 'Legacy image effect', 'overcustomise' ); ?><?php if ( ! empty( $filter->remove_background ) ) : ?><br><small><?php esc_html_e( 'Background removed', 'overcustomise' ); ?></small><?php endif; ?></td><td><?php echo ! empty( $filter->active ) ? esc_html__( 'Active', 'overcustomise' ) : esc_html__( 'Inactive', 'overcustomise' ); ?></td><td><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-image-filters&action=edit&id=' . (int) $filter->id ) ); ?>"><?php esc_html_e( 'Edit', 'overcustomise' ); ?></a> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=overcustomise-image-filters&action=toggle&id=' . (int) $filter->id . '&state=' . ( ! empty( $filter->active ) ? '0' : '1' ) ), 'oc_image_filter_toggle_' . (int) $filter->id ) ); ?>"><?php echo ! empty( $filter->active ) ? esc_html__( 'Deactivate', 'overcustomise' ) : esc_html__( 'Activate', 'overcustomise' ); ?></a> <a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=overcustomise-image-filters&action=delete&id=' . (int) $filter->id ), 'oc_image_filter_delete_' . (int) $filter->id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this filter?', 'overcustomise' ); ?>');"><?php esc_html_e( 'Delete', 'overcustomise' ); ?></a></td></tr><?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
		</div>
		<script>
		(function () {
			const button = document.getElementById('oc-test-ai-filter');
			if (!button) return;
			button.addEventListener('click', async function () {
				const status = document.getElementById('oc-ai-test-status');
				const result = document.getElementById('oc-ai-test-result');
				const file = document.getElementById('oc_filter_test_image').files[0];
				const prompt = document.getElementById('oc_filter_prompt').value.trim();
				if (!file || !prompt) { status.textContent = '<?php echo esc_js( __( 'Choose an image and enter a prompt.', 'overcustomise' ) ); ?>'; return; }
				button.disabled = true; status.textContent = '<?php echo esc_js( __( 'Generating...', 'overcustomise' ) ); ?>'; result.style.display = 'none';
				const body = new FormData(); body.append('action', 'oc_test_ai_image_filter'); body.append('nonce', '<?php echo esc_js( wp_create_nonce( 'oc-image-filter-test' ) ); ?>'); body.append('prompt', prompt); body.append('test_image', file); if (document.getElementById('oc_filter_remove_background').checked) body.append('remove_background', '1');
				try { const response = await fetch(ajaxurl, {method:'POST', body}); const json = await response.json(); if (!json.success) throw new Error(json.data?.message || '<?php echo esc_js( __( 'Test failed.', 'overcustomise' ) ); ?>'); result.querySelector('img').src = json.data.image; result.style.display = ''; status.textContent = '<?php echo esc_js( __( 'Generated with ', 'overcustomise' ) ); ?>' + json.data.model; }
				catch (error) { status.textContent = error.message || '<?php echo esc_js( __( 'Test failed.', 'overcustomise' ) ); ?>'; }
				finally { button.disabled = false; }
			});
		})();
		</script>
		<?php
	}

	private function get_filter( int $id ): ?object {
		foreach ( OC_DB::get_image_filters( false ) as $filter ) {
			if ( (int) $filter->id === $id ) return $filter;
		}
		return null;
	}

	private function handle_save(): bool {
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$prompt = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
		$remove_background = ! empty( $_POST['remove_background'] ) ? 1 : 0;
		$id = absint( $_POST['filter_id'] ?? 0 );
		if ( '' === $name || '' === trim( $prompt ) || strlen( $name ) > 100 || strlen( $prompt ) > 10000 ) return false;

		global $wpdb;
		$data = [ 'name' => $name, 'filter_key' => 'ai', 'value' => 1, 'prompt' => $prompt, 'remove_background' => $remove_background, 'active' => 1 ];
		if ( $id && ! $this->get_filter( $id ) ) {
			return false;
		}
		if ( $id ) {
			$result = $wpdb->update( $wpdb->prefix . 'oc_image_filters', $data, [ 'id' => $id ], [ '%s', '%s', '%f', '%s', '%d', '%d' ], [ '%d' ] );
		} else {
			$result = $wpdb->insert( $wpdb->prefix . 'oc_image_filters', $data, [ '%s', '%s', '%f', '%s', '%d', '%d' ] );
		}
		if ( false === $result ) return false;
		OC_DB::clear_image_filter_cache();
		return true;
	}

	private function handle_toggle(): void {
		$id = absint( $_GET['id'] ?? 0 ); $state = ! empty( $_GET['state'] ) ? 1 : 0;
		if ( ! $id || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'oc_image_filter_toggle_' . $id ) ) return;
		global $wpdb; $wpdb->update( $wpdb->prefix . 'oc_image_filters', [ 'active' => $state ], [ 'id' => $id ], [ '%d' ], [ '%d' ] ); OC_DB::clear_image_filter_cache(); wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-image-filters' ) ); exit;
	}

	private function handle_delete(): void {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! $id || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'oc_image_filter_delete_' . $id ) ) return;
		global $wpdb; $wpdb->delete( $wpdb->prefix . 'oc_image_filters', [ 'id' => $id ], [ '%d' ] ); OC_DB::clear_image_filter_cache(); wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-image-filters' ) ); exit;
	}
}
