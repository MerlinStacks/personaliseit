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
		$screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\Admin\Orders\PageController::class )->get_edit_screen_id();

		// Register on both legacy CPT screen and HPOS screen.
		foreach ( [ 'shop_order', $screen ] as $screen_id ) {
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

		foreach ( $items as $item_id => $item ) {
			$product_id = $item->get_product_id();
			$config     = OC_DB::get_config_by_product( $product_id );

			// Skip items with no customisation config.
			if ( ! $config ) {
				continue;
			}

			$print_files = OC_DB::get_print_files_for_item( $item_id );
			$areas       = OC_DB::get_print_areas( (int) $config->id );

			echo '<div style="border:1px solid #ddd;border-radius:4px;padding:12px 16px;margin-bottom:16px;">';
			printf(
				'<h4 style="margin:0 0 8px;">%s</h4>',
				esc_html( $item->get_name() . ' × ' . $item->get_quantity() )
			);

			// Customisation summary from order item meta.
			$customisation = $item->get_meta( '_oc_customisation', true );
			if ( $customisation && is_array( $customisation ) ) {
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

			if ( empty( $print_files ) ) {
				echo '<p style="color:#888;">' . esc_html__( 'No print files generated yet.', 'overcustomise' ) . '</p>';
			} else {
				foreach ( $print_files as $file ) {
					$area_label = '';
					foreach ( $areas as $area ) {
						if ( (int) $area->id === (int) $file->print_area_id ) {
							$area_label = $area->label;
							break;
						}
					}

					echo '<div style="margin-bottom:8px;padding:8px;background:#f9f9f9;border-radius:3px;">';
					printf(
						'<strong>%s</strong> &mdash; %s &mdash; <span style="color:%s;">%s</span>',
						esc_html( $area_label ?: __( 'Area', 'overcustomise' ) ),
						esc_html( ucfirst( str_replace( '_', ' ', $file->file_type ) ) ),
						esc_attr( $this->get_status_color( $file->file_status ) ),
						esc_html( $this->get_status_label( $file->file_status ) )
					);

					// Download button for ready files.
					if ( 'files_ready' === $file->file_status && $file->file_path ) {
						$download_url = add_query_arg( [
							'oc_download_file' => $file->id,
							'_wpnonce'         => wp_create_nonce( 'oc_download_' . $file->id ),
						], admin_url() );
						printf(
							' &nbsp;<a href="%s" class="button button-small">%s</a>',
							esc_url( $download_url ),
							esc_html__( 'Download', 'overcustomise' )
						);
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

	/** Handle DST/EMB file upload submitted from the order metabox. */
	public function handle_dst_upload( int $post_id ): void {
		if ( empty( $_POST['oc_dst_file_id'] ) || empty( $_FILES['oc_dst_file']['tmp_name'] ) ) {
			return;
		}

		$file_id = (int) $_POST['oc_dst_file_id'];

		if ( ! wp_verify_nonce( $_POST['_oc_dst_nonce'] ?? '', 'oc_dst_upload_' . $file_id ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$record = OC_DB::get_print_file( $file_id );
		if ( ! $record || 'awaiting_dst_upload' !== $record->file_status ) {
			return;
		}

		$file    = $_FILES['oc_dst_file'];
		$allowed = [ 'dst', 'emb', 'jef', 'vp3', 'pes', 'xxx' ];
		$ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed, true ) ) {
			return;
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return;
		}

		// Store the DST in the same order directory as the brief.
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/overcustomise/print-files/' . $record->order_id;
		wp_mkdir_p( $dir );

		$dest = $dir . '/' . $record->order_item_id . '-' . $record->print_area_id . '-dst.' . $ext;

		if ( move_uploaded_file( $file['tmp_name'], $dest ) ) {
			OC_DB::update_print_file( $file_id, [
				'file_path'   => $dest,
				'file_status' => 'files_ready',
			] );
			OC_Logger::info( "DST uploaded for print file #{$file_id}: {$dest}" );
		}
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
