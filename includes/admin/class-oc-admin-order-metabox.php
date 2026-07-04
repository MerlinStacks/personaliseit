<?php
/**
 * Print files meta box on the WooCommerce order edit page.
 * HPOS-compatible — uses wc_get_order(), never get_post().
 * Shows generated print files with download links, brief downloads, and DST upload.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Order_Metabox {

	public function register(): void {
		// Works for both legacy orders (shop_order CPT) and HPOS orders.
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );

		// DST file upload handler (fires on save_post / HPOS order save).
		add_action( 'save_post',            [ $this, 'handle_dst_upload' ], 10, 1 );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'handle_dst_upload' ], 10, 1 );
	}

	public function add_meta_box(): void {
		$screens    = [ 'shop_order' ];
		$hpos_class = '\\Automattic\\WooCommerce\\Internal\\Admin\\Orders\\PageController';

		if ( function_exists( 'wc_get_container' ) && class_exists( $hpos_class ) ) {
			try {
				$hpos_screen = wc_get_container()->get( $hpos_class )->get_edit_screen_id();
				if ( $hpos_screen ) {
					$screens[] = $hpos_screen;
				}
			} catch ( \Throwable $e ) {
				// HPOS container not ready — fall back to legacy screen only.
			}
		}

		foreach ( $screens as $screen_id ) {
			add_meta_box(
				'oc-print-files',
				__( 'OverCustomise — Print Files', 'overcustomise' ),
				[ $this, 'render' ],
				$screen_id,
				'normal',
				'high'
			);
		}
	}

	/** Render the meta box content. */
	public function render( $post_or_order ): void {
		// Support both legacy post object and HPOS order object.
		$order = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order instanceof \WC_Order ) {
			echo '<p>' . esc_html__( 'Could not load order.', 'overcustomise' ) . '</p>';
			return;
		}

		$items = $order->get_items();

		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No items in this order.', 'overcustomise' ) . '</p>';
			return;
		}

		echo '<style>@keyframes oc-spin{to{transform:rotate(360deg)}}</style>';

		foreach ( $items as $item_id => $item ) {
			$product_id = $item->get_product_id();
			$config_id     = (int) $item->get_meta( '_oc_config_id', true );
			$customisation = $item->get_meta( '_oc_customisation', true );
			$design_id     = 0;

			if ( is_array( $customisation ) && isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
				$design_id = (int) ( $customisation['designId'] ?? $item->get_meta( '_oc_design_id', true ) );
			}

			if ( ! $config_id ) {
				$config = OC_DB::get_config_by_product( $product_id );
				if ( $config ) {
					$config_id = (int) $config->id;
				}
			}

			$print_files  = OC_DB::get_print_files_for_item( $item_id );
			$legacy_areas = $config_id ? OC_DB::get_print_areas( $config_id ) : [];
			$design_areas = $design_id ? OC_DB::get_design_print_areas( $design_id ) : [];

			if ( empty( $print_files ) && is_array( $customisation ) && ( ! empty( $legacy_areas ) || ! empty( $design_areas ) ) ) {
				( new OC_Print_Generator() )->generate_for_order( $order );
				$print_files = OC_DB::get_print_files_for_item( $item_id );
			}

			// Skip items that have no OverCustomise data at all.
			if ( empty( $print_files ) && empty( $legacy_areas ) && empty( $design_areas ) && empty( $customisation ) ) {
				continue;
			}

			echo '<div style="border:1px solid #ddd;border-radius:4px;padding:12px 16px;margin-bottom:16px;">';
			printf(
				'<h4 style="margin:0 0 8px;">%s</h4>',
				esc_html( $item->get_name() . ' × ' . $item->get_quantity() )
			);

			// Customisation summary from order item meta.
			if ( $customisation && is_array( $customisation ) ) {
				if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
					$this->render_v2_customisation_summary( $customisation, $design_id );
				} else {
					echo '<ul style="margin:0 0 10px;padding-left:18px;">';
					foreach ( $customisation as $area_key => $area_data ) {
						if ( is_array( $area_data ) ) {
							// Nested structure: { areaKey: { text, fontId, color } }.
							$text      = $area_data['text']  ?? '';
							$color     = $area_data['color'] ?? '';
							$font_name = '';
							if ( ! empty( $area_data['fontId'] ) ) {
								global $wpdb;
								$font_name = (string) $wpdb->get_var( $wpdb->prepare(
									"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
									$area_data['fontId']
								) );
							}
							printf(
								'<li><strong>%s:</strong> %s%s%s</li>',
								esc_html( ucwords( str_replace( '-', ' ', $area_key ) ) ),
								esc_html( $text ),
								$font_name ? ' &mdash; ' . esc_html( $font_name ) : '',
								$color ? sprintf(
									' &mdash; <span style="display:inline-block;width:10px;height:10px;background:%s;border:1px solid #ccc;vertical-align:middle;border-radius:2px;"></span> %s',
									esc_attr( $color ),
									esc_html( $color )
								) : ''
							);
						} else {
							// Flat legacy fallback.
							printf(
								'<li><strong>%s:</strong> %s</li>',
								esc_html( ucfirst( str_replace( '_', ' ', $area_key ) ) ),
								esc_html( (string) $area_data )
							);
						}
					}
					echo '</ul>';
				}
			}

			if ( empty( $print_files ) ) {
				echo '<p style="color:#888;">' . esc_html__( 'No print files generated yet.', 'overcustomise' ) . '</p>';
			} else {
				foreach ( $print_files as $file ) {
					$area_label = $this->resolve_area_label(
						(int) $file->print_area_id,
						$legacy_areas,
						$design_areas,
						$design_id > 0
					);

					$queue_info = OC_Print_Queue::instance()->get_status( (int) $file->id );

					echo '<div style="margin-bottom:8px;padding:8px;background:#f9f9f9;border-radius:3px;">';
					printf(
						'<strong>%s</strong> &mdash; %s &mdash; <span style="color:%s;">%s</span>',
						esc_html( $area_label ?: __( 'Area', 'overcustomise' ) ),
						esc_html( ucfirst( str_replace( '_', ' ', $file->file_type ) ) ),
						esc_attr( $this->get_status_color( $file->file_status ) ),
						esc_html( $this->get_status_label( $file->file_status ) )
					);

					if ( $queue_info['in_queue'] ) {
						printf(
							' &nbsp;<span class="oc-queue-badge" style="display:inline-block;padding:2px 8px;background:#0073aa;color:#fff;border-radius:10px;font-size:11px;font-weight:600;">%s</span>',
							esc_html( sprintf( __( 'In Queue (#%d)', 'overcustomise' ), $queue_info['queue_position'] ) )
						);
					}

					if ( $queue_info['is_processing'] ) {
						echo ' &nbsp;<span class="oc-processing-badge" style="display:inline-block;padding:2px 8px;background:#9e6c00;color:#fff;border-radius:10px;font-size:11px;font-weight:600;">'
							. esc_html__( 'Processing', 'overcustomise' )
							. ' <span class="oc-spinner" style="display:inline-block;width:12px;height:12px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:oc-spin 0.8s linear infinite;"></span>'
							. '</span>';
					}

					// Download button for ready files.
					if ( 'files_ready' === $file->file_status && $file->file_path && file_exists( $file->file_path ) ) {
						$download_url = add_query_arg( [
							'oc_download_file' => $file->id,
							'_wpnonce'         => wp_create_nonce( 'oc_download_' . $file->id ),
						], admin_url() );
						printf(
							' &nbsp;<a href="%s" class="button button-small">%s</a>',
							esc_url( $download_url ),
							esc_html__( 'Download', 'overcustomise' )
						);
					} elseif ( 'files_ready' === $file->file_status ) {
						echo ' &nbsp;<em style="color:#888;">' . esc_html__( '(File missing on disk)', 'overcustomise' ) . '</em>';
					}

					if ( 'files_ready' === $file->file_status ) {
						$regen_url = add_query_arg( [
							'oc_regenerate' => $file->id,
							'_wpnonce'      => wp_create_nonce( 'oc_regenerate_' . $file->id ),
						], admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) );
						printf(
							' &nbsp;<a href="%s" class="button button-small">%s</a>',
							esc_url( $regen_url ),
							esc_html__( 'Regenerate', 'overcustomise' )
						);
					}

					// Inline thumbnail preview for files_ready.
					if ( 'files_ready' === $file->file_status ) {
						$this->render_thumbnail( $file, $order );
					}

					// Download brief + DST upload for embroidery awaiting manual digitising.
					if ( 'awaiting_dst_upload' === $file->file_status ) {
						if ( $file->file_path && file_exists( $file->file_path ) ) {
							$brief_url = add_query_arg( [
								'oc_download_file' => $file->id,
								'_wpnonce'         => wp_create_nonce( 'oc_download_' . $file->id ),
							], admin_url() );
							printf(
								' &nbsp;<a href="%s" class="button button-small">%s</a>',
								esc_url( $brief_url ),
								esc_html__( 'Download Brief', 'overcustomise' )
							);
						}

						// DST upload form.
						$upload_url = add_query_arg( [
							'post'        => $order->get_id(),
							'action'      => 'edit',
						], admin_url( 'post.php' ) );

						echo '<br><form method="post" enctype="multipart/form-data" style="display:inline;margin-left:8px;">';
						wp_nonce_field( 'oc_dst_upload_' . $file->id, '_oc_dst_nonce' );
						printf( '<input type="hidden" name="oc_dst_file_id" value="%d">', (int) $file->id );
						echo '<input type="file" name="oc_dst_file" accept=".dst,.emb,.jef,.vp3,.pes" style="font-size:11px;">';
						printf(
							'<input type="submit" class="button button-small" value="%s" style="margin-left:4px;">',
							esc_attr__( 'Upload DST', 'overcustomise' )
						);
						echo '</form>';
					}

					if ( 'expired' === $file->file_status ) {
						$regen_url = add_query_arg( [
							'oc_regenerate' => $file->id,
							'_wpnonce'      => wp_create_nonce( 'oc_regenerate_' . $file->id ),
						], admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) );
						printf(
							' &nbsp;<a href="%s" class="button button-small">%s</a>',
							esc_url( $regen_url ),
							esc_html__( 'Regenerate', 'overcustomise' )
						);
					}

					echo '</div>';
				}
			}

			echo '</div>';
		}
	}

	private function render_v2_customisation_summary( array $customisation, int $design_id ): void {
		$layers = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];

		if ( empty( $layers ) ) {
			return;
		}

		$layer_map = [];
		if ( $design_id > 0 ) {
			foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
				$layer_map[ (int) $layer->id ] = $layer;
			}
		}

		echo '<ul style="margin:0 0 10px;padding-left:18px;">';
		foreach ( $layers as $layer_id => $layer_data ) {
			if ( ! is_array( $layer_data ) ) {
				continue;
			}

			$type       = is_string( $layer_data['type'] ?? null ) ? $layer_data['type'] : 'layer';
			$layer_obj  = $layer_map[ (int) $layer_id ] ?? null;
			$label      = $layer_obj ? ( $layer_obj->label ?: ucfirst( $layer_obj->type ) ) : ucfirst( $type );
			$value_html = $this->v2_layer_display_value( $layer_data );

			if ( '' === $value_html ) {
				continue;
			}

			printf(
				'<li><strong>%s:</strong> %s</li>',
				esc_html( $label ),
				$value_html // already escaped/sanitized in helper.
			);
		}
		echo '</ul>';
	}

	private function v2_layer_display_value( array $layer_data ): string {
		$type = is_string( $layer_data['type'] ?? null ) ? $layer_data['type'] : '';

		if ( in_array( $type, [ 'text', 'textarea', 'spotify' ], true ) ) {
			$value     = trim( (string) ( $layer_data['value'] ?? '' ) );
			$font_name = '';
			$color     = '';
			if ( ! empty( $layer_data['fontId'] ) ) {
				global $wpdb;
				$font_name = (string) $wpdb->get_var( $wpdb->prepare(
					"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
					(int) $layer_data['fontId']
				) );
			}
			if ( ! empty( $layer_data['colorHex'] ) && is_string( $layer_data['colorHex'] ) ) {
				$color = sanitize_hex_color( $layer_data['colorHex'] ) ?: '';
			}
			if ( '' === $value ) {
				return '';
			}

			$html = esc_html( $value );
			if ( '' !== $font_name ) {
				$html .= ' &mdash; ' . esc_html( $font_name );
			}
			if ( '' !== $color ) {
				$html .= sprintf(
					' &mdash; <span style="display:inline-block;width:10px;height:10px;background:%s;border:1px solid #ccc;vertical-align:middle;border-radius:2px;"></span> %s',
					esc_attr( $color ),
					esc_html( $color )
				);
			}
			return $html;
		}

		if ( 'image' === $type && ! empty( $layer_data['attachmentId'] ) ) {
			$thumb = wp_get_attachment_image(
				(int) $layer_data['attachmentId'],
				[ 48, 48 ],
				false,
				[ 'style' => 'vertical-align:middle;border:1px solid #ddd;border-radius:2px;' ]
			);
			return $thumb ?: esc_html__( '[Image uploaded]', 'overcustomise' );
		}

		if ( 'clipart' === $type ) {
			$has_clipart = ! empty( $layer_data['clipartId'] ) || ! empty( $layer_data['clipartUrl'] );
			return $has_clipart ? esc_html__( '[Clipart selected]', 'overcustomise' ) : '';
		}

		return '';
	}

	private function resolve_area_label(
		int $print_area_id,
		array $legacy_areas,
		array $design_areas,
		bool $prefer_design
	): string {
		$search_sets = $prefer_design ? [ $design_areas, $legacy_areas ] : [ $legacy_areas, $design_areas ];

		foreach ( $search_sets as $areas ) {
			foreach ( $areas as $area ) {
				if ( (int) $area->id === $print_area_id ) {
					return (string) $area->label;
				}
			}
		}

		return '';
	}

	/** Handle DST/EMB file upload submitted from the order metabox. */
	public function handle_dst_upload( int $post_id ): void {
		// Bail on autosaves / cron / ajax without the expected POST.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Capability check first — cheapest guard and scoped to admins.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Require the nonce and file id together before touching any POST data.
		if ( empty( $_POST['_oc_dst_nonce'] ) || empty( $_POST['oc_dst_file_id'] ) ) {
			return;
		}

		$file_id = absint( $_POST['oc_dst_file_id'] );
		if ( ! $file_id ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_oc_dst_nonce'] ) ), 'oc_dst_upload_' . $file_id ) ) {
			return;
		}

		if ( empty( $_FILES['oc_dst_file']['tmp_name'] ) ) {
			return;
		}

		$record = OC_DB::get_print_file( $file_id );
		if ( ! $record || 'awaiting_dst_upload' !== $record->file_status ) {
			return;
		}

		$file = $_FILES['oc_dst_file'];

		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			OC_Logger::warning( "DST upload error code {$file['error']} for print file #{$file_id}." );
			return;
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return;
		}

		$original_name = isset( $file['name'] ) ? basename( (string) $file['name'] ) : '';
		if ( '' === $original_name ) {
			return;
		}

		$allowed = [ 'dst', 'emb', 'jef', 'vp3', 'pes', 'xxx' ];
		$ext     = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed, true ) ) {
			return;
		}

		// Size sanity cap — embroidery stitch files are tiny; 10 MB is generous.
		$max_bytes = 10 * 1024 * 1024;
		if ( (int) ( $file['size'] ?? 0 ) <= 0 || (int) $file['size'] > $max_bytes ) {
			return;
		}

		// Store the DST in the same order directory as the brief.
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/overcustomise/print-files/' . (int) $record->order_id;
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}

		$dest = $dir . '/' . (int) $record->order_item_id . '-' . (int) $record->print_area_id . '-dst.' . $ext;

		if ( move_uploaded_file( $file['tmp_name'], $dest ) ) {
			OC_DB::update_print_file( $file_id, [
				'file_path'   => $dest,
				'file_status' => 'files_ready',
			] );
			OC_Logger::info( "DST uploaded for print file #{$file_id}: {$dest}" );
		}
	}

	private function render_thumbnail( object $file, \WC_Order $order ): void {
		$thumb_url = null;

		if ( ! empty( $file->thumbnail_path ) && file_exists( $file->thumbnail_path ) ) {
			$thumb_url = $this->get_protected_file_url( $file->thumbnail_path, $file->id, 'oc_download_' );
		}

		echo '<div style="margin-top:6px;">';

		if ( $thumb_url ) {
			printf(
				'<a href="%s" class="thickbox" style="display:inline-block;"><img src="%s" width="150" style="border:1px solid #ddd;border-radius:3px;cursor:zoom-in;transition:opacity 0.15s;" onmouseover="this.style.opacity=\'0.75\'" onmouseout="this.style.opacity=\'1\'"></a>',
				esc_url( $thumb_url ),
				esc_url( $thumb_url )
			);
		} else {
			echo '<svg width="150" height="100" viewBox="0 0 150 100" style="border:1px solid #ddd;border-radius:3px;background:#fafafa;cursor:default;">';
			echo '<rect x="25" y="10" width="100" height="80" rx="4" fill="#e0e0e0" stroke="#ccc" stroke-width="1"/>';
			echo '<text x="75" y="52" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#888">PDF</text>';
			echo '<text x="75" y="68" text-anchor="middle" font-family="sans-serif" font-size="9" fill="#aaa">Preview</text>';
			echo '</svg>';
		}

		echo '</div>';
	}

	private function get_protected_file_url( string $file_path, int $file_id, string $nonce_action ): string {
		$upload_dir = wp_upload_dir();
		$base_url   = rtrim( $upload_dir['baseurl'], '/' );
		$base_dir   = rtrim( $upload_dir['basedir'], '/' );

		if ( strpos( realpath( $file_path ) ?: '', realpath( $base_dir ) ?: '' ) === 0 ) {
			$relative = substr( $file_path, strlen( $base_dir ) + 1 );
			return $base_url . '/' . $relative;
		}

		return admin_url( 'admin-ajax.php?action=oc_serve_thumb&file_id=' . $file_id . '&_wpnonce=' . wp_create_nonce( $nonce_action . $file_id ) );
	}

	private function get_status_label( string $status ): string {
		$labels = [
			'pending'               => __( 'Pending', 'overcustomise' ),
			'generating'            => __( 'Generating…', 'overcustomise' ),
			'brief_ready'           => __( 'Production Brief Ready', 'overcustomise' ),
			'awaiting_dst_upload'   => __( 'Awaiting DST Upload', 'overcustomise' ),
			'files_ready'           => __( 'Files Ready', 'overcustomise' ),
			'expired'               => __( 'Expired', 'overcustomise' ),
		];
		return $labels[ $status ] ?? ucfirst( $status );
	}

	private function get_status_color( string $status ): string {
		return match ( $status ) {
			'files_ready'         => '#1b7e34',
			'awaiting_dst_upload' => '#9e6c00',
			'brief_ready'         => '#0073aa',
			'generating'          => '#555',
			'expired'             => '#b32d2e',
			default               => '#888',
		};
	}
}
