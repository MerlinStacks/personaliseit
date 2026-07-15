<?php
/**
 * Database table creation and schema migrations.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_DB {

	/** Create (or upgrade) all plugin tables. */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		// ------------------------------------------------------------------
		// 1. Product customisation configurations
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_product_configs (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id    BIGINT UNSIGNED NOT NULL,
			custom_type   ENUM('text_only','photo_text') NOT NULL DEFAULT 'text_only',
			flat_rate     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			active        TINYINT(1) NOT NULL DEFAULT 1,
			created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY   product_id (product_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 2. Print areas (1–n per product config)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_print_areas (
			id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			config_id            BIGINT UNSIGNED NOT NULL,
			area_key             VARCHAR(50)  NOT NULL DEFAULT 'front',
			label                VARCHAR(100) NOT NULL DEFAULT 'Front',
			print_method         ENUM('engraving','uv','embroidery','sublimation') NOT NULL DEFAULT 'uv',
			engraving_material   VARCHAR(30) NOT NULL DEFAULT 'silver_metal',
			mockup_attachment_id BIGINT UNSIGNED DEFAULT NULL,
			canvas_x             INT NOT NULL DEFAULT 0,
			canvas_y             INT NOT NULL DEFAULT 0,
			canvas_w             INT NOT NULL DEFAULT 300,
			canvas_h             INT NOT NULL DEFAULT 300,
			canvas_rotation      INT NOT NULL DEFAULT 0,
			sort_order           INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY config_id (config_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 3. Fonts
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_fonts (
			id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name                 VARCHAR(100) NOT NULL,
			file_path            VARCHAR(500) NOT NULL,
			weight               VARCHAR(20)  NOT NULL DEFAULT 'normal',
			style                VARCHAR(20)  NOT NULL DEFAULT 'normal',
			embroidery_suitable  TINYINT(1) NOT NULL DEFAULT 0,
			active               TINYINT(1) NOT NULL DEFAULT 1,
			created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active_name (active, name)
		) $charset;" );

		// ------------------------------------------------------------------
		// 4. Font groups
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_font_groups (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(100) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 5. Font group items (many-to-many: group ↔ font)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_font_group_items (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id   BIGINT UNSIGNED NOT NULL,
			font_id    BIGINT UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY   group_font (group_id, font_id),
			KEY          group_id (group_id),
			KEY          font_id (font_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 6. Colours
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_colours (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(100) NOT NULL,
			hex        VARCHAR(7)   NOT NULL DEFAULT '#000000',
			active     TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 7. Colour groups
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_colour_groups (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(100) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 8. Colour group items (many-to-many: group ↔ colour)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_colour_group_items (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id   BIGINT UNSIGNED NOT NULL,
			colour_id  BIGINT UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY   group_colour (group_id, colour_id),
			KEY          group_id (group_id),
			KEY          colour_id (colour_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 9. Clipart items
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_clipart (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(100) NOT NULL,
			file_path  VARCHAR(500) NOT NULL,
			file_type  VARCHAR(10)  NOT NULL DEFAULT 'svg',
			colour_changeable TINYINT(1) NOT NULL DEFAULT 1,
			allowed_print_methods TEXT NULL,
			active     TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active_name (active, name)
		) $charset;" );

		// ------------------------------------------------------------------
		// 10. Clipart groups
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_clipart_groups (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(100) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 11. Clipart group items (many-to-many: group ↔ clipart)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_clipart_group_items (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id   BIGINT UNSIGNED NOT NULL,
			clipart_id BIGINT UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY   group_clipart (group_id, clipart_id),
			KEY          group_id (group_id),
			KEY          clipart_id (clipart_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 12. Designs (reusable customisation templates)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_designs (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name        VARCHAR(150) NOT NULL DEFAULT '',
			custom_type ENUM('text_only','photo_text') NOT NULL DEFAULT 'text_only',
			flat_rate   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			active      TINYINT(1) NOT NULL DEFAULT 1,
			clone_priority TINYINT(1) NOT NULL DEFAULT 0,
			created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY          active (active)
		) $charset;" );

		// ------------------------------------------------------------------
		// 13. Design print areas (one or more per design)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_design_print_areas (
			id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			design_id            BIGINT UNSIGNED NOT NULL,
			area_key             VARCHAR(50)  NOT NULL DEFAULT 'front',
			label                VARCHAR(100) NOT NULL DEFAULT 'Front',
			print_method         ENUM('engraving','uv','embroidery','sublimation') NOT NULL DEFAULT 'uv',
			engraving_material   VARCHAR(30) NOT NULL DEFAULT 'silver_metal',
			canvas_unit          VARCHAR(10) NOT NULL DEFAULT 'px',
			mockup_attachment_id BIGINT UNSIGNED DEFAULT NULL,
			canvas_x             INT NOT NULL DEFAULT 0,
			canvas_y             INT NOT NULL DEFAULT 0,
			canvas_w             INT NOT NULL DEFAULT 300,
			canvas_h             INT NOT NULL DEFAULT 300,
			canvas_dpi           INT NOT NULL DEFAULT 300,
			canvas_rotation      INT NOT NULL DEFAULT 0,
			sort_order           INT NOT NULL DEFAULT 0,
			visible              TINYINT(1) NOT NULL DEFAULT 1,
			locked               TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY          design_id (design_id),
			KEY          design_sort (design_id, sort_order)
		) $charset;" );

		// ------------------------------------------------------------------
		// 14. Product design assignments (product/variant → design)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_product_assignments (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			design_id  BIGINT UNSIGNED NOT NULL,
			design_variants LONGTEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY   product_variant (product_id, variant_id),
			KEY          design_id (design_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 15. Design layers (interactive zones within a print area)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_design_layers (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			design_id  BIGINT UNSIGNED NOT NULL,
			area_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			type       VARCHAR(20)  NOT NULL DEFAULT 'text',
			label      VARCHAR(100) NOT NULL DEFAULT '',
			x          INT NOT NULL DEFAULT 0,
			y          INT NOT NULL DEFAULT 0,
			w          INT NOT NULL DEFAULT 200,
			h          INT NOT NULL DEFAULT 50,
			sort_order INT NOT NULL DEFAULT 0,
			visible    TINYINT(1) NOT NULL DEFAULT 1,
			locked     TINYINT(1) NOT NULL DEFAULT 0,
			settings   TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY          design_id (design_id),
			KEY          area_id (area_id),
			KEY          design_area_sort (design_id, area_id, sort_order)
		) $charset;" );

		// ------------------------------------------------------------------
		// 16. Generated print files per order item
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_print_files (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id       BIGINT UNSIGNED NOT NULL,
			order_item_id  BIGINT UNSIGNED NOT NULL,
			print_area_id  BIGINT UNSIGNED NOT NULL,
			area_source    VARCHAR(20) NOT NULL DEFAULT 'unknown',
			row_index      INT UNSIGNED NOT NULL DEFAULT 0,
			row_key        VARCHAR(191) NOT NULL DEFAULT '',
			identity_key   CHAR(64) DEFAULT NULL,
			area_snapshot  LONGTEXT DEFAULT NULL,
			file_type      VARCHAR(20)  NOT NULL DEFAULT '',
			file_path      VARCHAR(500) DEFAULT NULL,
			thumbnail_path VARCHAR(500) DEFAULT NULL,
			file_status    ENUM('pending','generating','brief_ready','awaiting_dst_upload','files_ready','expired') NOT NULL DEFAULT 'pending',
			generated_at   DATETIME DEFAULT NULL,
			expires_at     DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY identity_key (identity_key),
			KEY order_id      (order_id),
			KEY order_item_id (order_item_id),
			KEY file_status   (file_status),
			KEY expires_at    (expires_at)
		) $charset;" );

		// ------------------------------------------------------------------
		// 17. Webhook endpoints
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_webhooks (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(100) NOT NULL DEFAULT '',
			url        VARCHAR(500) NOT NULL,
			events     TEXT NOT NULL,
			secret     VARCHAR(100) DEFAULT NULL,
			active     TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 18. VDP templates (one per design)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_vdp_templates (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			design_id     BIGINT UNSIGNED NOT NULL,
			csv_file_path VARCHAR(500) DEFAULT NULL,
			active        TINYINT(1) NOT NULL DEFAULT 0,
			created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY design_id (design_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 19. VDP fields (many per template)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_vdp_fields (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_id BIGINT UNSIGNED NOT NULL,
			field_name  VARCHAR(50) NOT NULL,
			layer_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sort_order  INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY template_id (template_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 20. Print job queue (async generation with retries)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_print_queue (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			print_file_id  BIGINT UNSIGNED DEFAULT NULL,
			order_id       BIGINT UNSIGNED NOT NULL,
			order_item_id  BIGINT UNSIGNED NOT NULL,
			print_area_id  BIGINT UNSIGNED NOT NULL,
			area_source    VARCHAR(20) NOT NULL DEFAULT 'unknown',
			row_index      INT UNSIGNED NOT NULL DEFAULT 0,
			area_data      TEXT NOT NULL,
			print_method   VARCHAR(20) NOT NULL DEFAULT '',
			status         ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
			attempts       INT NOT NULL DEFAULT 0,
			error_message  TEXT DEFAULT NULL,
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at   DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY print_file_id (print_file_id),
			KEY status (status),
			KEY status_id (status, id),
			KEY status_created (status, created_at),
			KEY order_item_id (order_item_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 21. Image filters available to upload layers
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_image_filters (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name        VARCHAR(100) NOT NULL,
			filter_key  VARCHAR(30) NOT NULL DEFAULT 'grayscale',
			value       DECIMAL(10,3) NOT NULL DEFAULT 1.000,
			active      TINYINT(1) NOT NULL DEFAULT 1,
			created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) $charset;" );
	}

	/** Run migrations if DB version is outdated. */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'oc_db_version', '0' );

		if ( version_compare( $installed, OC_DB_VERSION, '<' ) || ! self::print_pipeline_schema_ready() ) {
			$lock_name = 'oc_db_upgrade_lock';
			$locked_at = (int) get_option( $lock_name, 0 );
			if ( $locked_at > 0 && $locked_at < time() - 300 ) {
				delete_option( $lock_name );
			}
			if ( ! add_option( $lock_name, time(), '', false ) ) {
				return;
			}

			self::create_tables();

			global $wpdb;

			if ( version_compare( $installed, '1.9.0', '<' ) ) {
				$col = $wpdb->get_results(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
					 WHERE TABLE_SCHEMA = DATABASE()
					 AND TABLE_NAME = '{$wpdb->prefix}oc_print_files'
					 AND COLUMN_NAME = 'thumbnail_path'"
				);
				if ( empty( $col ) ) {
					$wpdb->query(
						"ALTER TABLE {$wpdb->prefix}oc_print_files
						 ADD COLUMN thumbnail_path VARCHAR(500) DEFAULT NULL AFTER file_path"
					);
				}
			}

			if ( version_compare( $installed, '1.10.0', '<' ) ) {
				foreach ( [ 'oc_print_areas', 'oc_design_print_areas' ] as $table ) {
					$table_name = $wpdb->prefix . $table;
					$col = $wpdb->get_results(
						"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
						 WHERE TABLE_SCHEMA = DATABASE()
						 AND TABLE_NAME = '{$table_name}'
						 AND COLUMN_NAME = 'canvas_rotation'"
					);
					if ( empty( $col ) ) {
						$wpdb->query(
							"ALTER TABLE {$table_name}
							 ADD COLUMN canvas_rotation INT NOT NULL DEFAULT 0 AFTER canvas_h"
						);
					}
				}
			}

			if ( version_compare( $installed, '1.11.1', '<' ) ) {
				$table_name = $wpdb->prefix . 'oc_design_print_areas';

				if ( ! self::column_exists( $table_name, 'canvas_unit' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN canvas_unit VARCHAR(10) NOT NULL DEFAULT 'px' AFTER print_method"
					);
				}

				if ( ! self::column_exists( $table_name, 'visible' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order"
					);
				}

				if ( ! self::column_exists( $table_name, 'locked' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER visible"
					);
				}

				$table_name = $wpdb->prefix . 'oc_design_layers';

				if ( ! self::column_exists( $table_name, 'visible' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order"
					);
				}

				if ( ! self::column_exists( $table_name, 'locked' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER visible"
					);
				}
			}

			if ( version_compare( $installed, '1.12.0', '<' ) ) {
				$table_name = $wpdb->prefix . 'oc_product_assignments';
				if ( ! self::column_exists( $table_name, 'design_variants' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN design_variants LONGTEXT DEFAULT NULL AFTER design_id"
					);
				}
			}

			if ( version_compare( $installed, '1.12.1', '<' ) ) {
				$table_name = $wpdb->prefix . 'oc_design_print_areas';
				if ( ! self::column_exists( $table_name, 'canvas_dpi' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN canvas_dpi INT NOT NULL DEFAULT 300 AFTER canvas_h"
					);
				}
			}

			if ( version_compare( $installed, '1.13.0', '<' ) ) {
				foreach ( [ 'oc_print_areas', 'oc_design_print_areas' ] as $table ) {
					$table_name = $wpdb->prefix . $table;
					if ( ! self::column_exists( $table_name, 'engraving_material' ) ) {
						$wpdb->query(
							"ALTER TABLE {$table_name}
							 ADD COLUMN engraving_material VARCHAR(30) NOT NULL DEFAULT 'silver_metal' AFTER print_method"
						);
					}
				}
			}

			if ( version_compare( $installed, '1.13.3', '<' ) ) {
				$table_name = $wpdb->prefix . 'oc_clipart';
				if ( ! self::column_exists( $table_name, 'colour_changeable' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN colour_changeable TINYINT(1) NOT NULL DEFAULT 1 AFTER file_type"
					);
				}
				if ( ! self::column_exists( $table_name, 'allowed_print_methods' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN allowed_print_methods TEXT NULL AFTER colour_changeable"
					);
				}
			}

			if ( version_compare( $installed, '1.13.5', '<' ) ) {
				$table_name = $wpdb->prefix . 'oc_designs';
				if ( ! self::column_exists( $table_name, 'clone_priority' ) ) {
					$wpdb->query(
						"ALTER TABLE {$table_name}
						 ADD COLUMN clone_priority TINYINT(1) NOT NULL DEFAULT 0 AFTER active"
					);
				}
			}

			if ( version_compare( $installed, '1.13.6', '<' ) ) {
				self::create_tables();
			}

			self::backfill_print_pipeline_schema();

			if ( self::print_pipeline_schema_ready() ) {
				update_option( 'oc_db_version', OC_DB_VERSION );
			} else {
				OC_Logger::error( 'Print pipeline database upgrade is incomplete; the database version was not advanced.' );
			}

			delete_option( $lock_name );
		}
	}

	/** Return whether requests may safely use the current print-pipeline schema. */
	public static function print_pipeline_available(): bool {
		if ( get_option( 'oc_db_upgrade_lock', 0 ) ) {
			return false;
		}

		$installed = get_option( 'oc_db_version', '0' );
		return ! version_compare( $installed, OC_DB_VERSION, '<' ) || self::print_pipeline_schema_ready();
	}

	/** Confirm every column required by the print pipeline is present. */
	private static function print_pipeline_schema_ready(): bool {
		global $wpdb;

		$required = [
			$wpdb->prefix . 'oc_print_files' => [ 'area_source', 'row_index', 'row_key', 'identity_key', 'area_snapshot' ],
			$wpdb->prefix . 'oc_print_queue' => [ 'print_file_id', 'area_source', 'row_index' ],
		];

		foreach ( $required as $table => $columns ) {
			foreach ( $columns as $column ) {
				if ( ! self::column_exists( $table, $column ) ) {
					return false;
				}
			}
		}

		return self::unique_index_exists( $wpdb->prefix . 'oc_print_files', 'identity_key' )
			&& self::unique_index_exists( $wpdb->prefix . 'oc_print_queue', 'print_file_id' );
	}

	/** Check for a named unique index. */
	private static function unique_index_exists( string $table_name, string $index_name ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0',
			$table_name,
			$index_name
		) );
	}

	/** Backfill only area sources that can be established without guessing. */
	private static function backfill_print_pipeline_schema(): void {
		global $wpdb;

		if ( ! self::print_pipeline_schema_ready() ) {
			return;
		}

		$files = $wpdb->prefix . 'oc_print_files';
		$queue = $wpdb->prefix . 'oc_print_queue';
		$legacy = $wpdb->prefix . 'oc_print_areas';
		$design = $wpdb->prefix . 'oc_design_print_areas';
		$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

		// Order-time customisation data is authoritative when both area tables share an ID.
		$wpdb->query(
			"UPDATE {$files} pf
			 JOIN {$order_itemmeta} oim ON oim.order_item_id = pf.order_item_id AND oim.meta_key = '_oc_customisation'
			 SET pf.area_source = CASE
				 WHEN oim.meta_value LIKE '%s:1:\"v\";i:2;%' OR oim.meta_value LIKE '%\"v\":2%' THEN 'design'
				 ELSE 'legacy'
			 END
			 WHERE pf.area_source = 'unknown'"
		);

		$wpdb->query(
			"UPDATE {$files} pf
			 SET area_source = CASE
				 WHEN EXISTS (SELECT 1 FROM {$design} da WHERE da.id = pf.print_area_id)
				  AND NOT EXISTS (SELECT 1 FROM {$legacy} la WHERE la.id = pf.print_area_id) THEN 'design'
				 WHEN EXISTS (SELECT 1 FROM {$legacy} la WHERE la.id = pf.print_area_id)
				  AND NOT EXISTS (SELECT 1 FROM {$design} da WHERE da.id = pf.print_area_id) THEN 'legacy'
				 ELSE 'unknown'
			 END
			 WHERE area_source = 'unknown'"
		);

		// Give the oldest copy of each existing identity the unique key. Historical
		// duplicates remain readable but cannot cause more duplicate generation.
		$wpdb->query(
			"UPDATE {$files} pf
			 LEFT JOIN {$files} earlier ON earlier.order_id = pf.order_id
				AND earlier.order_item_id = pf.order_item_id
				AND earlier.print_area_id = pf.print_area_id
				AND earlier.area_source = pf.area_source
				AND earlier.row_index = pf.row_index
				AND earlier.id < pf.id
			 SET pf.identity_key = SHA2(CONCAT(pf.order_id, ':', pf.order_item_id, ':', pf.area_source, ':', pf.print_area_id, ':', pf.row_index), 256)
			 WHERE pf.identity_key IS NULL AND pf.area_source <> 'unknown' AND earlier.id IS NULL"
		);

		$wpdb->query(
			"UPDATE {$queue} q
			 JOIN {$files} pf ON pf.id = q.print_file_id
			 SET q.area_source = pf.area_source, q.row_index = pf.row_index
			 WHERE q.area_source = 'unknown' AND pf.area_source <> 'unknown'"
		);

		$wpdb->query(
			"UPDATE {$queue} q
			 JOIN {$files} pf ON pf.id = (
				 SELECT candidate.id FROM {$files} candidate
				 WHERE candidate.order_id = q.order_id
				 AND candidate.order_item_id = q.order_item_id
				 AND candidate.print_area_id = q.print_area_id
				 ORDER BY candidate.id DESC LIMIT 1
			 )
			 SET q.print_file_id = pf.id, q.area_source = pf.area_source, q.row_index = pf.row_index
			 WHERE q.print_file_id IS NULL
			 AND pf.area_source <> 'unknown'
			 AND (SELECT COUNT(*) FROM {$files} matches
				 WHERE matches.order_id = q.order_id
				 AND matches.order_item_id = q.order_item_id
				 AND matches.print_area_id = q.print_area_id) = 1"
		);
	}

	private static function column_exists( string $table_name, string $column_name ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				$table_name,
				$column_name
			)
		);
	}

	// -------------------------------------------------------------------------
	// Helper query methods
	// -------------------------------------------------------------------------

	/** Fetch a product config row by WC product ID. */
	public static function get_config_by_product( int $product_id ): ?object {
		$cache_key = 'product_config_' . $product_id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_product_configs WHERE product_id = %d LIMIT 1",
				$product_id
			)
		) ?: null;
		OC_Cache::set( $cache_key, $row );
		return $row;
	}

	/** Fetch all print areas for a given config ID. */
	public static function get_print_areas( int $config_id ): array {
		$cache_key = 'print_areas_' . $config_id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_areas WHERE config_id = %d ORDER BY sort_order ASC",
				$config_id
			)
		) ?: [];
		OC_Cache::set( $cache_key, $results );
		return $results;
	}

	/** Fetch all colours (optionally active-only). */
	public static function get_colours( bool $active_only = true ): array {
		$cache_key = 'colours_' . ( $active_only ? 'active' : 'all' );
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		if ( $active_only ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_colours WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}oc_colours ORDER BY name ASC"
			) ?: [];
		}
		OC_Cache::set( $cache_key, $results );
		return $results;
	}

	/** Fetch all image filters (optionally active-only). */
	public static function get_image_filters( bool $active_only = true ): array {
		$cache_key = 'image_filters_' . ( $active_only ? 'active' : 'all' );
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}

		global $wpdb;
		if ( $active_only ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_image_filters WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}oc_image_filters ORDER BY name ASC"
			) ?: [];
		}

		OC_Cache::set( $cache_key, $results );
		return $results;
	}

	/** Clear cached image filter query results. */
	public static function clear_image_filter_cache(): void {
		OC_Cache::delete( 'image_filters_active' );
		OC_Cache::delete( 'image_filters_all' );
	}

	/** Fetch all colour groups with their associated colour IDs. */
	public static function get_colour_groups(): array {
		$cache_key = 'colour_groups';
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;

		$groups = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_colour_groups ORDER BY name ASC"
		) ?: [];

		if ( empty( $groups ) ) {
			OC_Cache::set( $cache_key, [] );
			return [];
		}

		$all_items = $wpdb->get_results(
			"SELECT group_id, colour_id FROM {$wpdb->prefix}oc_colour_group_items ORDER BY sort_order ASC"
		) ?: [];

		$by_group = [];
		foreach ( $all_items as $item ) {
			$by_group[ (int) $item->group_id ][] = (int) $item->colour_id;
		}

		foreach ( $groups as $group ) {
			$group->colour_ids = $by_group[ (int) $group->id ] ?? [];
		}

		OC_Cache::set( $cache_key, $groups );
		return $groups;
	}

	/** Fetch active colours available to the supplied colour group IDs. Empty IDs mean all active colours. */
	public static function get_colours_for_groups( array $group_ids ): array {
		$group_ids = array_values( array_filter( array_map( 'absint', $group_ids ) ) );

		if ( empty( $group_ids ) ) {
			return self::get_colours( true );
		}

		// A group assignment is explicit, so honour its members even if a colour has
		// since been marked inactive in the global colour list.
		$colours = self::get_colours( false );

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
		$allowed_ids  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT colour_id FROM {$wpdb->prefix}oc_colour_group_items WHERE group_id IN ($placeholders) GROUP BY colour_id ORDER BY MIN(sort_order) ASC",
				...$group_ids
			)
		) ?: [];
		$allowed_ids  = array_values( array_unique( array_map( 'absint', $allowed_ids ) ) );
		if ( empty( $allowed_ids ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$colours,
				fn( $colour ) => in_array( (int) ( $colour->id ?? 0 ), $allowed_ids, true )
			)
		);
	}

	/** Return the first active font ID, or 0 when no active font exists. */
	public static function get_first_active_font_id(): int {
		$fonts = self::get_fonts( true );
		$first = is_array( $fonts ) && ! empty( $fonts ) ? reset( $fonts ) : null;

		return is_object( $first ) && ! empty( $first->id ) ? absint( $first->id ) : 0;
	}

	/** Sanitise browser-captured per-area SVG snapshots before storing in cart/order meta. */
	public static function sanitise_area_snapshots( array $snapshots ): array {
		$clean = [];
		foreach ( $snapshots as $area_key => $snapshot ) {
			if ( ! is_array( $snapshot ) || ! is_string( $snapshot['svg'] ?? null ) ) {
				continue;
			}

			$key = is_scalar( $area_key ) ? sanitize_key( (string) $area_key ) : '';
			if ( '' === $key || strlen( $snapshot['svg'] ) > 512 * 1024 ) {
				continue;
			}

			try {
				$svg = OC_SVG_Sanitiser::sanitise( $snapshot['svg'] );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}

			if ( '' === $svg ) {
				continue;
			}

			$clean[ $key ] = [
				'format' => sanitize_key( is_string( $snapshot['format'] ?? null ) ? $snapshot['format'] : 'fabric-svg-v1' ),
				'unit'   => sanitize_key( is_string( $snapshot['unit'] ?? null ) ? $snapshot['unit'] : 'mockup_px' ),
				'scale'  => isset( $snapshot['scale'] ) ? (float) $snapshot['scale'] : 1.0,
				'svg'    => $svg,
			];
		}

		return $clean;
	}

	/** Fetch all font groups with their associated font IDs. */
	public static function get_font_groups(): array {
		$cache_key = 'font_groups';
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;

		$groups = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_font_groups ORDER BY name ASC"
		) ?: [];

		if ( empty( $groups ) ) {
			OC_Cache::set( $cache_key, [] );
			return [];
		}

		$all_items = $wpdb->get_results(
			"SELECT group_id, font_id FROM {$wpdb->prefix}oc_font_group_items ORDER BY sort_order ASC"
		) ?: [];

		$by_group = [];
		foreach ( $all_items as $item ) {
			$by_group[ (int) $item->group_id ][] = (int) $item->font_id;
		}

		foreach ( $groups as $group ) {
			$group->font_ids = $by_group[ (int) $group->id ] ?? [];
		}

		OC_Cache::set( $cache_key, $groups );
		return $groups;
	}

	/** Fetch all active fonts. */
	public static function get_fonts( bool $active_only = true ): array {
		$cache_key = 'fonts_v2_' . ( $active_only ? 'active' : 'all' );
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		if ( $active_only ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}oc_fonts ORDER BY name ASC"
			) ?: [];
		}
		OC_Cache::set( $cache_key, $results );
		return $results;
	}

	/** Fetch active font IDs assigned to any of the supplied font groups. */
	public static function get_font_ids_for_groups( array $group_ids ): array {
		$group_ids = array_values( array_unique( array_filter( array_map( 'absint', $group_ids ) ) ) );
		if ( empty( $group_ids ) ) {
			return [];
		}

		$active_font_ids = array_flip( array_map( 'intval', array_column( self::get_fonts( true ), 'id' ) ) );
		$font_ids        = [];

		foreach ( self::get_font_groups() as $group ) {
			if ( ! in_array( (int) $group->id, $group_ids, true ) ) {
				continue;
			}

			foreach ( (array) $group->font_ids as $font_id ) {
				$font_id = (int) $font_id;
				if ( isset( $active_font_ids[ $font_id ] ) && ! in_array( $font_id, $font_ids, true ) ) {
					$font_ids[] = $font_id;
				}
			}
		}

		return $font_ids;
	}

	/** Fetch print files for a given order item. */
	public static function get_print_files_for_item( int $order_item_id ): array {
		$cache_key = 'print_files_item_' . $order_item_id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_item_id = %d",
				$order_item_id
			)
		) ?: [];
		OC_Cache::set( $cache_key, $results );
		return $results;
	}

	/** Fetch a single print file record by ID. */
	public static function get_print_file( int $id ): ?object {
		$cache_key = 'print_file_' . $id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE id = %d LIMIT 1",
				$id
			)
		) ?: null;
		OC_Cache::set( $cache_key, $row );
		return $row;
	}

	/**
	 * Insert a new print file record.
	 * @param array $data  Column => value pairs.
	 * @return int  Inserted row ID.
	 */
	public static function insert_print_file( array $data ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'oc_print_files';

		if ( isset( $data['area_source'], $data['row_index'] ) ) {
			if ( ! self::print_pipeline_available() ) {
				OC_Logger::warning( 'Print file creation deferred while the database schema is being upgraded.' );
				return 0;
			}

			$allowed_columns = [ 'order_id', 'order_item_id', 'print_area_id', 'area_source', 'row_index', 'row_key', 'identity_key', 'area_snapshot', 'file_type', 'file_path', 'thumbnail_path', 'file_status', 'generated_at', 'expires_at' ];
			$data = array_intersect_key( $data, array_flip( $allowed_columns ) );
			$identity = implode( ':', [
				(int) ( $data['order_id'] ?? 0 ),
				(int) ( $data['order_item_id'] ?? 0 ),
				(string) $data['area_source'],
				(int) ( $data['print_area_id'] ?? 0 ),
				(int) $data['row_index'],
			] );
			$data['identity_key'] = hash( 'sha256', $identity );
			$columns = array_keys( $data );
			$values  = array_values( $data );
			$formats = array_map(
				static fn ( mixed $value ): string => is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' ),
				$values
			);
			$sql = 'INSERT IGNORE INTO ' . $table
				. ' (`' . implode( '`,`', $columns ) . '`) VALUES (' . implode( ',', $formats ) . ')';
			$wpdb->query( $wpdb->prepare( $sql, ...$values ) );
			$id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE identity_key = %s LIMIT 1",
				$data['identity_key']
			) );
		} else {
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}
		if ( ! empty( $data['order_item_id'] ) ) {
			OC_Cache::delete( 'print_files_item_' . (int) $data['order_item_id'] );
		}
		return $id;
	}

	/**
	 * Update an existing print file record.
	 * @param int   $id    Record ID.
	 * @param array $data  Column => value pairs to update.
	 */
	public static function update_print_file( int $id, array $data ): void {
		global $wpdb;
		$existing = self::get_print_file( $id );
		$wpdb->update( $wpdb->prefix . 'oc_print_files', $data, [ 'id' => $id ] );
		OC_Cache::delete( 'print_file_' . $id );
		if ( $existing && ! empty( $existing->order_item_id ) ) {
			OC_Cache::delete( 'print_files_item_' . (int) $existing->order_item_id );
		}
		if ( ! empty( $data['order_item_id'] ) ) {
			OC_Cache::delete( 'print_files_item_' . (int) $data['order_item_id'] );
		}
	}

	/** Fetch all layers for a design, ordered by area then sort_order. */
	public static function get_design_layers( int $design_id ): array {
		$cache_key = 'design_layers_' . $design_id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_design_layers
			 WHERE design_id = %d
			 ORDER BY area_id ASC, sort_order ASC",
			$design_id
		) ) ?: [];
		OC_Cache::set( $cache_key, $results, OC_Cache::TTL_SHORT );
		return $results;
	}

	/** Fetch all clipart (optionally active-only). */
	public static function get_clipart( bool $active_only = true ): array {
		$cache_key = 'clipart_' . ( $active_only ? 'active' : 'all' );
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		if ( $active_only ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_clipart WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}oc_clipart ORDER BY name ASC"
			) ?: [];
		}
		OC_Cache::set( $cache_key, $results );
		return $results;
	}

	/** Fetch all clipart groups with their clipart IDs. */
	public static function get_clipart_groups(): array {
		$cache_key = 'clipart_groups';
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;

		$groups = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_clipart_groups ORDER BY name ASC"
		) ?: [];

		if ( empty( $groups ) ) {
			OC_Cache::set( $cache_key, [] );
			return [];
		}

		$all_items = $wpdb->get_results(
			"SELECT group_id, clipart_id FROM {$wpdb->prefix}oc_clipart_group_items ORDER BY sort_order ASC"
		) ?: [];

		$by_group = [];
		foreach ( $all_items as $item ) {
			$by_group[ (int) $item->group_id ][] = (int) $item->clipart_id;
		}

		foreach ( $groups as $group ) {
			$group->clipart_ids = $by_group[ (int) $group->id ] ?? [];
		}

		OC_Cache::set( $cache_key, $groups );
		return $groups;
	}

	// ── Designs ───────────────────────────────────────────────────────────────

	/** Fetch a single design by ID. */
	public static function get_design( int $id ): ?object {
		$cache_key = 'design_' . $id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_designs WHERE id = %d LIMIT 1", $id
		) ) ?: null;
		OC_Cache::set( $cache_key, $row, OC_Cache::TTL_SHORT );
		return $row;
	}

	/** Fetch all designs (optionally active-only). */
	public static function get_designs( bool $active_only = false ): array {
		$cache_key = 'designs_' . ( $active_only ? 'active' : 'all' );
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		if ( $active_only ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_designs WHERE active = %d ORDER BY clone_priority DESC, name ASC",
					1
				)
			) ?: [];
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}oc_designs ORDER BY clone_priority DESC, name ASC"
			) ?: [];
		}
		OC_Cache::set( $cache_key, $results, OC_Cache::TTL_SHORT );
		return $results;
	}

	/** Fetch all designs with their print area counts. */
	public static function get_designs_with_area_counts(): array {
		$cache_key = 'designs_area_counts';
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT d.*, COUNT(a.id) AS area_count
			 FROM {$wpdb->prefix}oc_designs d
			 LEFT JOIN {$wpdb->prefix}oc_design_print_areas a ON a.design_id = d.id
			 GROUP BY d.id
			 ORDER BY d.clone_priority DESC, d.name ASC"
		) ?: [];
		OC_Cache::set( $cache_key, $results, OC_Cache::TTL_SHORT );
		return $results;
	}

	/** Fetch a page of designs with their print area counts. */
	public static function get_designs_with_area_counts_paginated( int $page = 1, int $per_page = 50, string $search = '' ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = trim( $search );
		$where    = '';
		$args     = [];

		if ( '' !== $search ) {
			$where  = ' WHERE d.name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$total_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}oc_designs d" . $where;
		$total     = (int) ( empty( $args ) ? $wpdb->get_var( $total_sql ) : $wpdb->get_var( $wpdb->prepare( $total_sql, ...$args ) ) );

		$items_sql = "SELECT d.*, COUNT(a.id) AS area_count
			 FROM {$wpdb->prefix}oc_designs d
			 LEFT JOIN {$wpdb->prefix}oc_design_print_areas a ON a.design_id = d.id
			 {$where}
			 GROUP BY d.id
			 ORDER BY d.clone_priority DESC, d.name ASC
			 LIMIT %d OFFSET %d";
		$items     = $wpdb->get_results( $wpdb->prepare( $items_sql, ...array_merge( $args, [ $per_page, $offset ] ) ) ) ?: [];

		return [
			'items'       => $items,
			'total'       => $total,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		];
	}

	/** Fetch print areas for a design. */
	public static function get_design_print_areas( int $design_id ): array {
		$cache_key = 'design_areas_' . $design_id;
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id = %d ORDER BY sort_order ASC",
			$design_id
		) ) ?: [];
		OC_Cache::set( $cache_key, $results, OC_Cache::TTL_SHORT );
		return $results;
	}

	// ── Product assignments ───────────────────────────────────────────────────

	/** Fetch all product design assignments keyed as [product_id][variant_id]. */
	public static function get_all_assignments(): array {
		$cache_key = 'all_assignments_v2';
		$cached    = OC_Cache::get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT product_id, variant_id, design_id, design_variants FROM {$wpdb->prefix}oc_product_assignments"
		) ?: [];

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->product_id ][ (int) $row->variant_id ] = [
				'design_id'       => (int) $row->design_id,
				'design_variants' => (string) ( $row->design_variants ?? '' ),
			];
		}
		OC_Cache::set( $cache_key, $map );
		return $map;
	}

	/** Fetch product design assignments for a specific set of parent product IDs. */
	public static function get_assignments_for_product_ids( array $product_ids ): array {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		if ( empty( $product_ids ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, variant_id, design_id, design_variants FROM {$wpdb->prefix}oc_product_assignments WHERE product_id IN ($placeholders)",
				...$product_ids
			)
		) ?: [];

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->product_id ][ (int) $row->variant_id ] = [
				'design_id'       => (int) $row->design_id,
				'design_variants' => (string) ( $row->design_variants ?? '' ),
			];
		}

		return $map;
	}

	/**
	 * Resolve the design assignment for a product/variation.
	 *
	 * Priority: exact variant assignment, parent assignment, then optionally the
	 * first variant assignment for initial variable-product page rendering.
	 */
	public static function get_assignment_for_product( int $product_id, int $variant_id = 0, bool $allow_variant_fallback = false ): ?object {
		global $wpdb;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		if ( $product->is_type( 'variation' ) ) {
			$variant_id = $product_id;
			$product_id = $product->get_parent_id();
		}

		$cache_key = 'assignment_' . $product_id . '_' . $variant_id . '_' . ( $allow_variant_fallback ? '1' : '0' );
		$cached    = OC_Cache::get( $cache_key );
		if ( '__oc_no_assignment__' === $cached ) {
			return null;
		}
		if ( null !== $cached ) {
			return $cached;
		}

		if ( $variant_id > 0 ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_product_assignments
				 WHERE product_id = %d AND variant_id = %d LIMIT 1",
				$product_id,
				$variant_id
			) );
			if ( $row ) {
				OC_Cache::set( $cache_key, $row );
				return $row;
			}
		}

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_product_assignments
			 WHERE product_id = %d AND variant_id = 0 LIMIT 1",
			$product_id
		) );
		if ( $row ) {
			OC_Cache::set( $cache_key, $row );
			return $row;
		}

		if ( $allow_variant_fallback && $product->is_type( 'variable' ) ) {
			$variation_ids = $product->get_children();
			if ( ! empty( $variation_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}oc_product_assignments
						 WHERE product_id = %d AND variant_id IN ($placeholders)
						 ORDER BY variant_id ASC LIMIT 1",
						$product_id,
						...$variation_ids
					)
				);
				if ( $row ) {
					OC_Cache::set( $cache_key, $row );
					return $row;
				}
			}
		}

		OC_Cache::set( $cache_key, '__oc_no_assignment__' );
		return null;
	}

	/** Check whether an assignment allows the supplied design, including enabled design variants. */
	public static function assignment_allows_design( object $assignment, int $design_id ): bool {
		if ( $design_id <= 0 ) {
			return false;
		}

		if ( $design_id === (int) ( $assignment->design_id ?? 0 ) ) {
			return true;
		}

		$variants = json_decode( (string) ( $assignment->design_variants ?? '' ), true );
		if ( ! is_array( $variants ) ) {
			return false;
		}

		foreach ( $variants as $variant ) {
			if ( $design_id === absint( $variant['designId'] ?? 0 ) ) {
				return true;
			}
		}

		return false;
	}

	/** Insert or update a product/variant → design assignment. */
	public static function upsert_assignment( int $product_id, int $variant_id, int $design_id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}oc_product_assignments (product_id, variant_id, design_id)
			 VALUES (%d, %d, %d)
			 ON DUPLICATE KEY UPDATE design_id = VALUES(design_id)",
			$product_id, $variant_id, $design_id
		) );
		OC_Cache::delete( 'all_assignments_v2' );
		OC_Cache::flush_pattern( 'assignment_' );
	}

	/** Update enabled customer-selectable design variants for a product/variant assignment. */
	public static function update_assignment_variants( int $product_id, int $variant_id, array $variants ): void {
		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}oc_product_assignments",
			[ 'design_variants' => wp_json_encode( array_values( $variants ) ) ],
			[ 'product_id' => $product_id, 'variant_id' => $variant_id ],
			[ '%s' ],
			[ '%d', '%d' ]
		);
		OC_Cache::delete( 'all_assignments_v2' );
		OC_Cache::flush_pattern( 'assignment_' );
	}

	/** Remove a product/variant assignment (unassign design). */
	public static function delete_assignment( int $product_id, int $variant_id ): void {
		global $wpdb;
		$wpdb->delete(
			"{$wpdb->prefix}oc_product_assignments",
			[ 'product_id' => $product_id, 'variant_id' => $variant_id ],
			[ '%d', '%d' ]
		);
		OC_Cache::delete( 'all_assignments_v2' );
		OC_Cache::flush_pattern( 'assignment_' );
	}

	/** Fetch pending queue jobs, ordered by creation time, limited to $limit. */
	public static function get_pending_queue_jobs( int $limit = 5 ): array {
		global $wpdb;
		$now = current_time( 'mysql', true );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_queue
				 WHERE status = 'pending'
				 AND (processed_at IS NULL OR processed_at <= %s)
				 ORDER BY created_at ASC
				 LIMIT %d",
				$now,
				$limit
			)
		) ?: [];
	}

	/** Atomically claim one due queue job and return its post-claim state. */
	public static function claim_queue_job( int $id, int $max_attempts ): ?object {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}oc_print_queue
			 SET status = 'processing', attempts = attempts + 1, processed_at = %s
			 WHERE id = %d AND status = 'pending' AND attempts < %d
			 AND (processed_at IS NULL OR processed_at <= %s)",
			$now,
			$id,
			$max_attempts,
			$now
		) );

		return 1 === $claimed ? self::get_queue_job( $id ) : null;
	}

	/** Fetch queue jobs for admin management. */
	public static function get_queue_jobs( string $status = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$allowed_statuses = [ 'pending', 'processing', 'done', 'failed' ];
		if ( in_array( $status, $allowed_statuses, true ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_print_queue WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
					$status,
					$limit,
					$offset
				)
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_queue ORDER BY id DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		) ?: [];
	}

	/** Count queue jobs for admin management. */
	public static function count_queue_jobs( string $status = '' ): int {
		global $wpdb;

		$allowed_statuses = [ 'pending', 'processing', 'done', 'failed' ];
		if ( in_array( $status, $allowed_statuses, true ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_queue WHERE status = %s",
				$status
			) );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_queue" );
	}

	/** Count queue jobs by status for admin summary cards. */
	public static function get_queue_counts(): array {
		global $wpdb;

		$counts = [
			'all'        => self::count_queue_jobs(),
			'pending'    => 0,
			'processing' => 0,
			'done'       => 0,
			'failed'     => 0,
		];

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}oc_print_queue GROUP BY status" ) ?: [];
		foreach ( $rows as $row ) {
			$status = (string) $row->status;
			if ( array_key_exists( $status, $counts ) ) {
				$counts[ $status ] = (int) $row->total;
			}
		}

		return $counts;
	}

	/** Fetch generating print files that no longer have an active queue job. */
	public static function get_orphaned_generating_print_files( int $limit = 20 ): array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT pf.* FROM {$wpdb->prefix}oc_print_files pf
			 LEFT JOIN {$wpdb->prefix}oc_print_queue pq
			 ON (pq.print_file_id = pf.id OR (pq.print_file_id IS NULL
			 AND pq.order_id = pf.order_id
			 AND pq.order_item_id = pf.order_item_id
			 AND pq.print_area_id = pf.print_area_id
			 AND pq.area_source = pf.area_source
			 AND pq.row_index = pf.row_index))
			 AND pq.status IN ('pending','processing')
			 WHERE pf.file_status = 'generating'
			 AND pq.id IS NULL
			 ORDER BY pf.generated_at ASC, pf.id ASC
			 LIMIT %d",
			$limit
		) ) ?: [];
	}

	/** Count generating print files that no longer have an active queue job. */
	public static function count_orphaned_generating_print_files(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files pf
			 LEFT JOIN {$wpdb->prefix}oc_print_queue pq
			 ON (pq.print_file_id = pf.id OR (pq.print_file_id IS NULL
			 AND pq.order_id = pf.order_id
			 AND pq.order_item_id = pf.order_item_id
			 AND pq.print_area_id = pf.print_area_id
			 AND pq.area_source = pf.area_source
			 AND pq.row_index = pf.row_index))
			 AND pq.status IN ('pending','processing')
			 WHERE pf.file_status = 'generating'
			 AND pq.id IS NULL"
		);
	}

	/** Fetch a single queue job by ID. */
	public static function get_queue_job( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_queue WHERE id = %d LIMIT 1",
				$id
			)
		) ?: null;
	}

	/** Update an existing queue job record. */
	public static function update_queue_job( int $id, array $data ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'oc_print_queue', $data, [ 'id' => $id ] );
	}

	/** Fetch all queue jobs for a given order item. */
	public static function get_queue_status_for_item( int $order_item_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_queue WHERE order_item_id = %d ORDER BY id DESC",
				$order_item_id
			)
		) ?: [];
	}

	// -------------------------------------------------------------------------
	// VDP helpers
	// -------------------------------------------------------------------------

	/** Fetch a VDP template for a design. */
	public static function get_vdp_template( int $design_id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_vdp_templates WHERE design_id = %d LIMIT 1",
				$design_id
			)
		) ?: null;
	}

	/** Fetch all VDP fields for a template. */
	public static function get_vdp_fields( int $template_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_vdp_fields WHERE template_id = %d ORDER BY sort_order ASC",
				$template_id
			)
		) ?: [];
	}

	/** Insert or update a VDP template. */
	public static function upsert_vdp_template( array $data ): bool {
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}oc_vdp_templates (design_id, csv_file_path, active)
			 VALUES (%d, %s, %d)
			 ON DUPLICATE KEY UPDATE csv_file_path = VALUES(csv_file_path), active = VALUES(active)",
			$data['design_id'],
			$data['csv_file_path'] ?? null,
			$data['active'] ?? 0
		) );

		return false !== $result;
	}

	/** Delete a VDP template and its fields. */
	public static function delete_vdp_template( int $design_id ): bool {
		global $wpdb;
		$template = self::get_vdp_template( $design_id );
		if ( $template ) {
			if ( ! self::delete_vdp_fields( (int) $template->id ) ) {
				return false;
			}
		}
		$result = $wpdb->delete(
			"{$wpdb->prefix}oc_vdp_templates",
			[ 'design_id' => $design_id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/** Insert a single VDP field. */
	public static function insert_vdp_field( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'oc_vdp_fields',
			[
				'template_id' => $data['template_id'],
				'field_name'  => $data['field_name'],
				'layer_id'    => $data['layer_id'] ?? 0,
				'sort_order'  => $data['sort_order'] ?? 0,
			]
		);
		return (int) $wpdb->insert_id;
	}

	/** Delete all VDP fields for a template. */
	public static function delete_vdp_fields( int $template_id ): bool {
		global $wpdb;
		$result = $wpdb->delete(
			"{$wpdb->prefix}oc_vdp_fields",
			[ 'template_id' => $template_id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Webhook helpers
	// -------------------------------------------------------------------------

	/** Fetch all active webhooks. */
	public static function get_active_webhooks(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_webhooks WHERE active = 1"
		) ?: [];
	}

	/** Fetch a single webhook by ID. */
	public static function get_webhook( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_webhooks WHERE id = %d LIMIT 1",
				$id
			)
		) ?: null;
	}

	public static function drop_all_tables(): void {
		global $wpdb;

		$tables = [
			'oc_print_queue',
			'oc_image_filters',
			'oc_webhooks',
			'oc_vdp_fields',
			'oc_vdp_templates',
			'oc_print_files',
			'oc_design_layers',
			'oc_product_assignments',
			'oc_design_print_areas',
			'oc_designs',
			'oc_clipart_group_items',
			'oc_clipart_groups',
			'oc_clipart',
			'oc_colour_group_items',
			'oc_colour_groups',
			'oc_colours',
			'oc_font_group_items',
			'oc_font_groups',
			'oc_fonts',
			'oc_print_areas',
			'oc_product_configs',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
		}
	}
}
