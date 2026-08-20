<?php
/**
 * Print files meta box on the WooCommerce order edit page.
 * HPOS-compatible — uses wc_get_order(), never get_post().
 * Shows generated print files with download and regeneration links.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Order_Metabox {

	public function register(): void {
		// Works for both legacy orders (shop_order CPT) and HPOS orders.
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );

	}

	public function add_meta_box(): void {
		$screens = [ 'shop_order' ];

		if ( function_exists( 'wc_get_container' ) && class_exists( \Automattic\WooCommerce\Internal\Admin\Orders\PageController::class ) ) {
			try {
				$hpos_screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\Admin\Orders\PageController::class )->get_edit_screen_id();
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
		$has_missing_print_files = false;
		$has_queue_work          = false;

		foreach ( $items as $item_id => $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
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

			$legacy_area_methods = [];
			foreach ( $legacy_areas as $legacy_area ) {
				$legacy_area_methods[ sanitize_key( (string) ( $legacy_area->area_key ?? '' ) ) ] = sanitize_key( (string) ( $legacy_area->print_method ?? '' ) );
			}

			if ( empty( $print_files ) && is_array( $customisation ) && ( ! empty( $legacy_areas ) || ! empty( $design_areas ) ) ) {
				$has_missing_print_files = true;
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
							$text  = is_scalar( $area_data['text'] ?? null ) ? (string) $area_data['text'] : '';
							$color = is_string( $area_data['color'] ?? null ) ? sanitize_hex_color( $area_data['color'] ) : '';
							if ( 'engraving' === ( $legacy_area_methods[ sanitize_key( (string) $area_key ) ] ?? '' ) ) {
								$color = '';
							}
							$font_name = '';
							if ( ! empty( $area_data['fontId'] ) ) {
								$font_name = OC_DB::get_font_name( absint( $area_data['fontId'] ) );
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
									esc_html( is_scalar( $area_data ) ? (string) $area_data : '' )
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
					$has_queue_work = $has_queue_work || ! empty( $queue_info['in_queue'] ) || ! empty( $queue_info['has_failed_job'] );

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

					// Download button for ready files and legacy embroidery files that still exist.
					$downloadable_statuses = [ 'files_ready', 'brief_ready', 'awaiting_dst_upload' ];
					if ( in_array( (string) $file->file_status, $downloadable_statuses, true ) && $file->file_path && file_exists( $file->file_path ) ) {
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

					$can_regenerate = in_array( (string) $file->file_status, [ 'files_ready', 'expired', 'brief_ready', 'awaiting_dst_upload' ], true )
						|| ( in_array( (string) $file->file_status, [ 'generating', 'pending' ], true ) && ! $queue_info['in_queue'] && ! $queue_info['is_processing'] );
					if ( $can_regenerate ) {
						echo '&nbsp;';
						$this->render_print_action_form(
							'oc_regenerate_print_file',
							[ 'file_id' => (int) $file->id ],
							'oc_regenerate_' . (int) $file->id,
							__( 'Regenerate Print File', 'overcustomise' )
						);
					}

					// Inline thumbnail preview for files_ready.
					if ( 'files_ready' === $file->file_status ) {
						$this->render_thumbnail( $file );
					}

					echo '</div>';
				}
			}

			echo '</div>';
		}

		if ( $has_missing_print_files ) {
			echo '<p>';
			$this->render_print_action_form(
				'oc_generate_print_files',
				[ 'order_id' => (int) $order->get_id() ],
				'oc_generate_print_files_' . (int) $order->get_id(),
				__( 'Generate Missing Print Files', 'overcustomise' ),
				'button button-primary'
			);
			echo '</p>';
		}
		if ( $has_queue_work ) {
			echo '<p>';
			$this->render_print_action_form(
				'oc_process_print_queue_order',
				[ 'order_id' => (int) $order->get_id() ],
				'oc_process_print_queue_order_' . (int) $order->get_id(),
				__( 'Process This Order Queue', 'overcustomise' )
			);
			echo '</p>';
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
		$print_method_map = $this->layer_print_method_map( $customisation, $layer_map, $design_id );

		echo '<ul style="margin:0 0 10px;padding-left:18px;">';
		foreach ( $layers as $layer_id => $layer_data ) {
			if ( ! is_array( $layer_data ) ) {
				continue;
			}

			$type       = is_string( $layer_data['type'] ?? null ) ? $layer_data['type'] : 'layer';
			$layer_obj  = $layer_map[ (int) $layer_id ] ?? null;
			if ( $this->is_fixed_clipart_layer( $layer_obj, $layer_data ) ) {
				continue;
			}
			$label      = $layer_obj ? ( ! empty( $layer_obj->label ) ? $layer_obj->label : ucfirst( (string) $layer_obj->type ) ) : ucfirst( $type );
			$value_html = $this->v2_layer_display_value( $layer_data, $layer_obj, $print_method_map[ (int) $layer_id ] ?? '' );

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

	private function v2_layer_display_value( array $layer_data, ?object $layer = null, string $print_method = '' ): string {
		$type        = is_string( $layer_data['type'] ?? null ) ? $layer_data['type'] : '';
		$show_colour = 'engraving' !== sanitize_key( $print_method );

		if ( in_array( $type, [ 'text', 'textarea', 'spotify' ], true ) ) {
			$value     = is_scalar( $layer_data['value'] ?? null ) ? trim( (string) $layer_data['value'] ) : '';
			$font_name = '';
			if ( ! empty( $layer_data['fontId'] ) ) {
				$font_name = OC_DB::get_font_name( absint( $layer_data['fontId'] ) );
			}
			if ( '' === $value ) {
				return '';
			}

			$html = esc_html( $value );
			if ( '' !== $font_name && $this->customer_can_change_layer_setting( $layer, 'allow_font_change' ) ) {
				$html .= ' &mdash; ' . esc_html( $font_name );
			}
			$colour_html = $show_colour && $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) ? $this->colour_display_value( $layer_data ) : '';
			if ( '' !== $colour_html ) {
				$html .= ' &mdash; ' . $colour_html;
			}
			return $html;
		}

		if ( in_array( $type, [ 'image', 'clipmask' ], true ) && ! empty( $layer_data['attachmentId'] ) ) {
			$attachment_id = absint( $layer_data['previewAttachmentId'] ?? $layer_data['attachmentId'] );
			$url           = OC_Upload_Handler::attachment_access_url( $attachment_id );
			$thumb         = $url ? sprintf(
				'<img src="%s" alt="" width="48" height="48" loading="lazy" style="vertical-align:middle;border:1px solid #ddd;border-radius:2px;" />',
				esc_url( $url )
			) : '';
			$html          = '' !== $thumb ? $thumb : esc_html__( '[Image uploaded]', 'overcustomise' );
			$colour_html   = $show_colour && $this->image_layer_has_order_colour( $layer_data, $layer )
				? $this->colour_display_value( $layer_data, $this->legacy_linked_image_colour_requires_lookup( $layer_data, $layer ) )
				: '';
			return '' !== $colour_html ? $html . ' &mdash; ' . $colour_html : $html;
		}

		if ( 'clipart' === $type ) {
			$has_clipart = ! empty( $layer_data['clipartId'] ) || ! empty( $layer_data['clipartUrl'] );
			if ( ! $has_clipart ) {
				return '';
			}

			$html        = esc_html__( '[Clipart selected]', 'overcustomise' );
			$colour_html = $show_colour && $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) ? $this->colour_display_value( $layer_data ) : '';
			return '' !== $colour_html ? $html . ' &mdash; ' . $colour_html : $html;
		}

		if ( 'lineart' === $type ) {
			return $show_colour && $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) ? $this->colour_display_value( $layer_data ) : '';
		}

		return '';
	}

	/** Resolve each v2 layer's production method, preferring the order-time snapshot. */
	private function layer_print_method_map( array $customisation, array $layer_map, int $design_id ): array {
		$methods     = [];
		$render_spec = is_array( $customisation['renderSpec'] ?? null ) ? $customisation['renderSpec'] : [];
		foreach ( is_array( $render_spec['areas'] ?? null ) ? $render_spec['areas'] : [] as $area ) {
			if ( ! is_array( $area ) ) {
				continue;
			}
			$print_method = sanitize_key( (string) ( $area['printMethod'] ?? '' ) );
			foreach ( is_array( $area['layers'] ?? null ) ? $area['layers'] : [] as $layer ) {
				$layer_id = is_array( $layer ) ? absint( $layer['id'] ?? 0 ) : 0;
				if ( $layer_id > 0 && '' !== $print_method ) {
					$methods[ $layer_id ] = $print_method;
				}
			}
		}

		$area_methods = [];
		foreach ( $design_id > 0 ? OC_DB::get_design_print_areas( $design_id ) : [] as $area ) {
			$area_methods[ (int) $area->id ] = sanitize_key( (string) $area->print_method );
		}
		foreach ( $layer_map as $layer_id => $layer ) {
			$area_id = (int) ( $layer->area_id ?? 0 );
			if ( ! isset( $methods[ $layer_id ] ) && isset( $area_methods[ $area_id ] ) ) {
				$methods[ $layer_id ] = $area_methods[ $area_id ];
			}
		}

		return $methods;
	}

	/** Return an escaped colour swatch and hex value for order admin summaries. */
	private function colour_display_value( array $layer_data, bool $verify_name = false ): string {
		$colour = ! empty( $layer_data['colorHex'] ) && is_string( $layer_data['colorHex'] )
			? sanitize_hex_color( $layer_data['colorHex'] )
			: '';

		if ( '' === $colour ) {
			return '';
		}

		global $wpdb;
		$colour_name = is_scalar( $layer_data['colorName'] ?? null )
			? sanitize_text_field( (string) $layer_data['colorName'] )
			: '';
		if ( '' === $colour_name || $verify_name ) {
			$canonical_names = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT name FROM {$wpdb->prefix}oc_colours WHERE LOWER(hex) = LOWER(%s) ORDER BY name ASC LIMIT 2",
				$colour
			) );
			$canonical_names = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', is_array( $canonical_names ) ? $canonical_names : [] ) ) ) );
			$colour_name = 1 === count( $canonical_names ) ? $canonical_names[0] : '';
		}

		return sprintf(
			'<span style="display:inline-block;width:10px;height:10px;background:%s;border:1px solid #ccc;vertical-align:middle;border-radius:2px;"></span> %s',
			esc_attr( $colour ),
			esc_html( '' !== $colour_name ? $colour_name : $colour )
		);
	}

	/** Layer settings default to customer-changeable unless explicitly disabled. */
	private function customer_can_change_layer_setting( ?object $layer, string $setting_key ): bool {
		if ( ! $layer || empty( $layer->settings ) ) {
			return true;
		}

		$settings = json_decode( (string) $layer->settings, true );
		if ( ! is_array( $settings ) || ! array_key_exists( $setting_key, $settings ) ) {
			return true;
		}

		return ! empty( $settings[ $setting_key ] );
	}

	/** Whether a recolourable image has a customer-selected or linked production colour. */
	private function image_layer_has_order_colour( array $layer_data, ?object $layer ): bool {
		$type = sanitize_key( (string) ( $layer_data['type'] ?? $layer->type ?? '' ) );
		if ( 'image' !== $type || ! sanitize_hex_color( (string) ( $layer_data['colorHex'] ?? '' ) ) ) {
			return false;
		}
		if ( ! empty( $layer_data['colourLinked'] ) ) {
			return true;
		}
		if ( ! $layer ) {
			return false;
		}
		$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
		return is_array( $settings ) && ! empty( $settings['enable_image_colour'] )
			&& ( $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) || ! empty( $layer_data['colourLinked'] ) || ! empty( $settings['colour_link_group'] ) );
	}

	/** Whether an old linked payload needs its stale target colour name resolved by hex. */
	private function legacy_linked_image_colour_requires_lookup( array $layer_data, ?object $layer ): bool {
		if ( ! empty( $layer_data['colourLinked'] ) || ! $layer ) {
			return false;
		}
		$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
		return is_array( $settings ) && ! empty( $settings['enable_image_colour'] )
			&& empty( $settings['allow_colour_change'] ) && ! empty( $settings['colour_link_group'] );
	}

	/** Do not show fixed default clipart as customer-selected order data. */
	private function is_fixed_clipart_layer( ?object $layer, array $layer_data ): bool {
		if ( 'clipart' !== ( $layer_data['type'] ?? '' ) || empty( $layer_data['clipartId'] ) || ! $layer ) {
			return false;
		}

		$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
		if ( ! is_array( $settings ) ) {
			return false;
		}

		return array_key_exists( 'allow_clipart_change', $settings )
			&& empty( $settings['allow_clipart_change'] )
			&& ! $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' );
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

	private function render_thumbnail( object $file ): void {
		$thumb_url = null;

		if ( ! empty( $file->thumbnail_path ) && file_exists( $file->thumbnail_path ) ) {
			$thumb_url = add_query_arg(
				[
					'action'    => 'oc_serve_print_thumbnail',
					'file_id'   => (int) $file->id,
					'_wpnonce'  => wp_create_nonce( 'oc_thumbnail_' . (int) $file->id ),
				],
				admin_url( 'admin-ajax.php' )
			);
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

	/** Render a POST submit button that uses the surrounding order edit form. */
	private function render_print_action_form( string $action, array $fields, string $nonce_action, string $label, string $class = 'button button-small' ): void {
		$name  = (string) array_key_first( $fields );
		$value = $fields[ $name ] ?? '';
		$url   = add_query_arg(
			[ 'action' => $action, 'oc_print_nonce' => wp_create_nonce( $nonce_action ), $name => $value ],
			admin_url( 'admin-post.php' )
		);
		echo '<button type="submit" name="action" value="' . esc_attr( $action )
			. '" class="' . esc_attr( $class ) . '" formmethod="post" formaction="' . esc_url( $url ) . '" formnovalidate>'
			. esc_html( $label ) . '</button>';
	}

	private function get_status_label( string $status ): string {
		$labels = [
			'pending'               => __( 'Pending', 'overcustomise' ),
			'generating'            => __( 'Generating…', 'overcustomise' ),
			'brief_ready'           => __( 'Legacy Brief Ready', 'overcustomise' ),
			'awaiting_dst_upload'   => __( 'Legacy DST Pending', 'overcustomise' ),
			'files_ready'           => __( 'Files Ready', 'overcustomise' ),
			'expired'               => __( 'Expired', 'overcustomise' ),
			'failed'                => __( 'Failed', 'overcustomise' ),
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
			'failed'              => '#b32d2e',
			default               => '#888',
		};
	}
}
