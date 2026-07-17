<?php
/**
 * Database table creation and schema migrations.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_DB {

	private const PRINT_FILE_CACHE_GROUP = 'oc_print_files';
	private const PRINT_FILE_CACHE_TTL = 3600;

	private static ?bool $schema_ready = null;

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
			file_status    ENUM('pending','generating','brief_ready','awaiting_dst_upload','files_ready','expired','failed') NOT NULL DEFAULT 'pending',
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
			area_data      LONGTEXT NOT NULL,
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
			KEY status_due (status, processed_at, created_at, id),
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
			prompt      LONGTEXT DEFAULT NULL,
			active      TINYINT(1) NOT NULL DEFAULT 1,
			created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) $charset;" );
	}

	/** Run migrations if DB version is outdated. */
	public static function maybe_upgrade(): void {
		$lock_name = 'oc_db_upgrade_lock';
		self::clear_stale_upgrade_lock( $lock_name );

		$installed = (string) get_option( 'oc_db_version', '0' );
		$outdated  = version_compare( $installed, OC_DB_VERSION, '<' );

		if ( ! $outdated ) {
			return;
		}

		$lock_owner = self::acquire_upgrade_lock( $lock_name );
		if ( null === $lock_owner ) {
			return;
		}

		try {
			$installed = (string) get_option( 'oc_db_version', '0' );
			self::$schema_ready = null;
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

			self::repair_current_print_schema();
			self::$schema_ready = null;
			$backfilled = self::backfill_print_pipeline_schema();

			if ( $backfilled && self::print_pipeline_schema_ready() ) {
				update_option( 'oc_db_version', OC_DB_VERSION );
				if ( version_compare( (string) get_option( 'oc_db_version', '0' ), OC_DB_VERSION, '<' ) ) {
					self::log_schema_error( 'The database schema is current, but its version option could not be saved.' );
				}
			} else {
				self::log_schema_error( 'Database upgrade is incomplete; the database version was not advanced.' );
			}
		} catch ( \Throwable $e ) {
			self::log_schema_error( 'Database upgrade failed: ' . $e->getMessage() );
		} finally {
			self::release_upgrade_lock( $lock_name, $lock_owner );
		}
	}

	/** Return whether requests may safely use the current print-pipeline schema. */
	public static function print_pipeline_available(): bool {
		if ( get_option( 'oc_db_upgrade_lock', 0 ) ) {
			return false;
		}

		return ! version_compare( (string) get_option( 'oc_db_version', '0' ), OC_DB_VERSION, '<' );
	}

	/** Confirm every current plugin column and index required at runtime is present. */
	private static function print_pipeline_schema_ready(): bool {
		if ( null !== self::$schema_ready ) {
			return self::$schema_ready;
		}

		global $wpdb;

		$required_columns = [
			$wpdb->prefix . 'oc_product_configs' => [ 'id', 'product_id', 'custom_type', 'flat_rate', 'active', 'created_at', 'updated_at' ],
			$wpdb->prefix . 'oc_print_areas' => [ 'id', 'config_id', 'area_key', 'label', 'print_method', 'engraving_material', 'mockup_attachment_id', 'canvas_x', 'canvas_y', 'canvas_w', 'canvas_h', 'canvas_rotation', 'sort_order' ],
			$wpdb->prefix . 'oc_fonts' => [ 'id', 'name', 'file_path', 'weight', 'style', 'embroidery_suitable', 'active', 'created_at' ],
			$wpdb->prefix . 'oc_font_groups' => [ 'id', 'name', 'created_at' ],
			$wpdb->prefix . 'oc_font_group_items' => [ 'id', 'group_id', 'font_id', 'sort_order' ],
			$wpdb->prefix . 'oc_colours' => [ 'id', 'name', 'hex', 'active', 'created_at' ],
			$wpdb->prefix . 'oc_colour_groups' => [ 'id', 'name', 'created_at' ],
			$wpdb->prefix . 'oc_colour_group_items' => [ 'id', 'group_id', 'colour_id', 'sort_order' ],
			$wpdb->prefix . 'oc_clipart' => [ 'id', 'name', 'file_path', 'file_type', 'colour_changeable', 'allowed_print_methods', 'active', 'created_at' ],
			$wpdb->prefix . 'oc_clipart_groups' => [ 'id', 'name', 'created_at' ],
			$wpdb->prefix . 'oc_clipart_group_items' => [ 'id', 'group_id', 'clipart_id', 'sort_order' ],
			$wpdb->prefix . 'oc_designs' => [ 'id', 'name', 'custom_type', 'flat_rate', 'active', 'clone_priority', 'created_at', 'updated_at' ],
			$wpdb->prefix . 'oc_design_print_areas' => [ 'id', 'design_id', 'area_key', 'label', 'print_method', 'engraving_material', 'canvas_unit', 'mockup_attachment_id', 'canvas_x', 'canvas_y', 'canvas_w', 'canvas_h', 'canvas_dpi', 'canvas_rotation', 'sort_order', 'visible', 'locked' ],
			$wpdb->prefix . 'oc_product_assignments' => [ 'id', 'product_id', 'variant_id', 'design_id', 'design_variants' ],
			$wpdb->prefix . 'oc_design_layers' => [ 'id', 'design_id', 'area_id', 'type', 'label', 'x', 'y', 'w', 'h', 'sort_order', 'visible', 'locked', 'settings', 'created_at' ],
			$wpdb->prefix . 'oc_print_files' => [ 'id', 'order_id', 'order_item_id', 'print_area_id', 'area_source', 'row_index', 'row_key', 'identity_key', 'area_snapshot', 'file_type', 'file_path', 'thumbnail_path', 'file_status', 'generated_at', 'expires_at' ],
			$wpdb->prefix . 'oc_webhooks' => [ 'id', 'name', 'url', 'events', 'secret', 'active', 'created_at' ],
			$wpdb->prefix . 'oc_vdp_templates' => [ 'id', 'design_id', 'csv_file_path', 'active', 'created_at' ],
			$wpdb->prefix . 'oc_vdp_fields' => [ 'id', 'template_id', 'field_name', 'layer_id', 'sort_order' ],
			$wpdb->prefix . 'oc_print_queue' => [ 'id', 'print_file_id', 'order_id', 'order_item_id', 'print_area_id', 'area_source', 'row_index', 'area_data', 'print_method', 'status', 'attempts', 'error_message', 'created_at', 'processed_at' ],
			$wpdb->prefix . 'oc_image_filters' => [ 'id', 'name', 'filter_key', 'value', 'prompt', 'active', 'created_at' ],
		];

		$table_placeholders = implode( ',', array_fill( 0, count( $required_columns ), '%s' ) );
		$column_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$table_placeholders})",
			...array_keys( $required_columns )
		) );
		if ( ! is_array( $column_rows ) ) {
			self::$schema_ready = false;
			return false;
		}

		$columns = [];
		foreach ( $column_rows as $row ) {
			$columns[ (string) $row->TABLE_NAME ][ (string) $row->COLUMN_NAME ] = $row;
		}

		foreach ( $required_columns as $table => $names ) {
			foreach ( $names as $name ) {
				if ( ! isset( $columns[ $table ][ $name ] ) ) {
					self::$schema_ready = false;
					return false;
				}
			}
		}

		$area_data = $columns[ $wpdb->prefix . 'oc_print_queue' ]['area_data'];
		$file_status = strtolower( (string) $columns[ $wpdb->prefix . 'oc_print_files' ]['file_status']->COLUMN_TYPE );
		if ( 'longtext' !== strtolower( (string) $area_data->DATA_TYPE ) || 'NO' !== strtoupper( (string) $area_data->IS_NULLABLE ) || ! str_contains( $file_status, "'failed'" ) ) {
			self::$schema_ready = false;
			return false;
		}

		$required_indexes = [
			$wpdb->prefix . 'oc_product_configs' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'product_id' => [ true, [ 'product_id' ] ] ],
			$wpdb->prefix . 'oc_print_areas' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'config_id' => [ false, [ 'config_id' ] ] ],
			$wpdb->prefix . 'oc_fonts' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'active_name' => [ false, [ 'active', 'name' ] ] ],
			$wpdb->prefix . 'oc_font_groups' => [ 'PRIMARY' => [ true, [ 'id' ] ] ],
			$wpdb->prefix . 'oc_font_group_items' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'group_font' => [ true, [ 'group_id', 'font_id' ] ], 'group_id' => [ false, [ 'group_id' ] ], 'font_id' => [ false, [ 'font_id' ] ] ],
			$wpdb->prefix . 'oc_colours' => [ 'PRIMARY' => [ true, [ 'id' ] ] ],
			$wpdb->prefix . 'oc_colour_groups' => [ 'PRIMARY' => [ true, [ 'id' ] ] ],
			$wpdb->prefix . 'oc_colour_group_items' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'group_colour' => [ true, [ 'group_id', 'colour_id' ] ], 'group_id' => [ false, [ 'group_id' ] ], 'colour_id' => [ false, [ 'colour_id' ] ] ],
			$wpdb->prefix . 'oc_clipart' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'active_name' => [ false, [ 'active', 'name' ] ] ],
			$wpdb->prefix . 'oc_clipart_groups' => [ 'PRIMARY' => [ true, [ 'id' ] ] ],
			$wpdb->prefix . 'oc_clipart_group_items' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'group_clipart' => [ true, [ 'group_id', 'clipart_id' ] ], 'group_id' => [ false, [ 'group_id' ] ], 'clipart_id' => [ false, [ 'clipart_id' ] ] ],
			$wpdb->prefix . 'oc_designs' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'active' => [ false, [ 'active' ] ] ],
			$wpdb->prefix . 'oc_design_print_areas' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'design_id' => [ false, [ 'design_id' ] ], 'design_sort' => [ false, [ 'design_id', 'sort_order' ] ] ],
			$wpdb->prefix . 'oc_product_assignments' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'product_variant' => [ true, [ 'product_id', 'variant_id' ] ], 'design_id' => [ false, [ 'design_id' ] ] ],
			$wpdb->prefix . 'oc_design_layers' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'design_id' => [ false, [ 'design_id' ] ], 'area_id' => [ false, [ 'area_id' ] ], 'design_area_sort' => [ false, [ 'design_id', 'area_id', 'sort_order' ] ] ],
			$wpdb->prefix . 'oc_print_files' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'identity_key' => [ true, [ 'identity_key' ] ], 'order_id' => [ false, [ 'order_id' ] ], 'order_item_id' => [ false, [ 'order_item_id' ] ], 'file_status' => [ false, [ 'file_status' ] ], 'expires_at' => [ false, [ 'expires_at' ] ] ],
			$wpdb->prefix . 'oc_webhooks' => [ 'PRIMARY' => [ true, [ 'id' ] ] ],
			$wpdb->prefix . 'oc_vdp_templates' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'design_id' => [ true, [ 'design_id' ] ] ],
			$wpdb->prefix . 'oc_vdp_fields' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'template_id' => [ false, [ 'template_id' ] ] ],
			$wpdb->prefix . 'oc_print_queue' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'print_file_id' => [ true, [ 'print_file_id' ] ], 'status' => [ false, [ 'status' ] ], 'status_id' => [ false, [ 'status', 'id' ] ], 'status_created' => [ false, [ 'status', 'created_at' ] ], 'status_due' => [ false, [ 'status', 'processed_at', 'created_at', 'id' ] ], 'order_item_id' => [ false, [ 'order_item_id' ] ] ],
			$wpdb->prefix . 'oc_image_filters' => [ 'PRIMARY' => [ true, [ 'id' ] ], 'active' => [ false, [ 'active' ] ] ],
		];

		$index_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
			 FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$table_placeholders})
			 ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
			...array_keys( $required_columns )
		) );
		if ( ! is_array( $index_rows ) ) {
			self::$schema_ready = false;
			return false;
		}

		$indexes = [];
		foreach ( $index_rows as $row ) {
			$table = (string) $row->TABLE_NAME;
			$name  = (string) $row->INDEX_NAME;
			$indexes[ $table ][ $name ]['unique'] = 0 === (int) $row->NON_UNIQUE;
			$indexes[ $table ][ $name ]['columns'][ (int) $row->SEQ_IN_INDEX ] = (string) $row->COLUMN_NAME;
		}

		foreach ( $required_indexes as $table => $definitions ) {
			foreach ( $definitions as $name => [ $unique, $index_columns ] ) {
				if ( ! isset( $indexes[ $table ][ $name ] ) ) {
					self::$schema_ready = false;
					return false;
				}
				ksort( $indexes[ $table ][ $name ]['columns'] );
				if ( $unique !== $indexes[ $table ][ $name ]['unique'] || $index_columns !== array_values( $indexes[ $table ][ $name ]['columns'] ) ) {
					self::$schema_ready = false;
					return false;
				}
			}
		}

		self::$schema_ready = true;
		return true;
	}

	/** Repair schema details dbDelta cannot always change reliably. */
	private static function repair_current_print_schema(): void {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'oc_print_queue';
		$files_table = $wpdb->prefix . 'oc_print_files';
		$area_data   = self::column_definition( $queue_table, 'area_data' );
		if ( $area_data && 'longtext' !== strtolower( (string) $area_data->DATA_TYPE ) ) {
			$wpdb->query( "ALTER TABLE `{$queue_table}` MODIFY COLUMN area_data LONGTEXT NOT NULL" );
		}

		$file_status = self::column_definition( $files_table, 'file_status' );
		if ( $file_status && ! str_contains( strtolower( (string) $file_status->COLUMN_TYPE ), "'failed'" ) ) {
			$wpdb->query( "ALTER TABLE `{$files_table}` MODIFY COLUMN file_status ENUM('pending','generating','brief_ready','awaiting_dst_upload','files_ready','expired','failed') NOT NULL DEFAULT 'pending'" );
		}

		if ( ! self::index_matches( $queue_table, 'status_due', false, [ 'status', 'processed_at', 'created_at', 'id' ] ) ) {
			if ( self::index_exists( $queue_table, 'status_due' ) ) {
				$wpdb->query( "ALTER TABLE `{$queue_table}` DROP INDEX status_due" );
			}
			$wpdb->query( "ALTER TABLE `{$queue_table}` ADD KEY status_due (status, processed_at, created_at, id)" );
		}
	}

	/** Return one column's current database definition. */
	private static function column_definition( string $table_name, string $column_name ): ?object {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$table_name,
			$column_name
		) ) ?: null;
	}

	/** Return whether a named index exists. */
	private static function index_exists( string $table_name, string $index_name ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s LIMIT 1',
			$table_name,
			$index_name
		) );
	}

	/** Return whether a named index has the expected uniqueness and columns. */
	private static function index_matches( string $table_name, string $index_name, bool $unique, array $columns ): bool {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT NON_UNIQUE, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s ORDER BY SEQ_IN_INDEX',
			$table_name,
			$index_name
		) );
		if ( empty( $rows ) ) {
			return false;
		}

		return $unique === ( 0 === (int) $rows[0]->NON_UNIQUE )
			&& $columns === array_map( static fn ( object $row ): string => (string) $row->COLUMN_NAME, $rows );
	}

	/** Atomically acquire the upgrade lock and return this process's owner token. */
	private static function acquire_upgrade_lock( string $lock_name ): ?string {
		self::clear_stale_upgrade_lock( $lock_name );
		if ( '' !== (string) get_option( $lock_name, '' ) ) {
			return null;
		}

		$owner = time() . '|' . ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'oc-', true ) );
		return add_option( $lock_name, $owner, '', false ) ? $owner : null;
	}

	/** Remove an abandoned or malformed upgrade lock without touching an active owner. */
	private static function clear_stale_upgrade_lock( string $lock_name ): void {
		$current = (string) get_option( $lock_name, '' );
		if ( '' === $current ) {
			return;
		}

		$locked_at = (int) strtok( $current, '|' );
		if ( $locked_at <= 0 || $locked_at < time() - 300 ) {
			self::delete_owned_option( $lock_name, $current );
		}
	}

	/** Release the upgrade lock only when it still belongs to this process. */
	private static function release_upgrade_lock( string $lock_name, string $owner ): void {
		self::delete_owned_option( $lock_name, $owner );
	}

	/** Conditionally delete an option without allowing an old owner to remove a new lock. */
	private static function delete_owned_option( string $option_name, string $expected_value ): bool {
		global $wpdb;

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$option_name,
			$expected_value
		) );
		if ( 1 === $deleted ) {
			wp_cache_delete( $option_name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			return true;
		}

		return false;
	}

	/** Log an upgrade failure without assuming the normal plugin bootstrap has loaded the logger. */
	private static function log_schema_error( string $message ): void {
		if ( class_exists( 'OC_Logger', false ) ) {
			call_user_func( [ 'OC_Logger', 'error' ], $message );
			return;
		}

		error_log( 'OverCustomise: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/** Backfill only area sources that can be established without guessing. */
	private static function backfill_print_pipeline_schema(): bool {
		global $wpdb;

		if ( ! self::print_pipeline_schema_ready() ) {
			return false;
		}

		$files = $wpdb->prefix . 'oc_print_files';
		$queue = $wpdb->prefix . 'oc_print_queue';
		$legacy = $wpdb->prefix . 'oc_print_areas';
		$design = $wpdb->prefix . 'oc_design_print_areas';
		$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

		// Order-time customisation data is authoritative when both area tables share an ID.
		$results   = [];
		$results[] = $wpdb->query(
			"UPDATE {$files} pf
			 JOIN {$order_itemmeta} oim ON oim.order_item_id = pf.order_item_id AND oim.meta_key = '_oc_customisation'
			 SET pf.area_source = CASE
				 WHEN oim.meta_value LIKE '%s:1:\"v\";i:2;%' OR oim.meta_value LIKE '%\"v\":2%' THEN 'design'
				 ELSE 'legacy'
			 END
			 WHERE pf.area_source = 'unknown'"
		);

		$results[] = $wpdb->query(
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
		$results[] = $wpdb->query(
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

		$results[] = $wpdb->query(
			"UPDATE {$queue} q
			 JOIN {$files} pf ON pf.id = q.print_file_id
			 SET q.area_source = pf.area_source, q.row_index = pf.row_index
			 WHERE q.area_source = 'unknown' AND pf.area_source <> 'unknown'"
		);

		$results[] = $wpdb->query(
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

		return ! in_array( false, $results, true );
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
		$cache_key  = 'print_files_item_' . $order_item_id;
		$generation = wp_cache_get_last_changed( self::PRINT_FILE_CACHE_GROUP );
		$found      = false;
		$cached     = wp_cache_get( $generation . ':' . $cache_key, self::PRINT_FILE_CACHE_GROUP, false, $found );
		if ( $found ) {
			return $cached;
		}
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_item_id = %d",
				$order_item_id
			)
		) ?: [];
		// Use the generation captured before the query. A concurrent writer can
		// advance the group without this stale result entering the new generation.
		wp_cache_set( $generation . ':' . $cache_key, $results, self::PRINT_FILE_CACHE_GROUP, self::PRINT_FILE_CACHE_TTL );
		return $results;
	}

	/** Fetch a single print file record by ID. */
	public static function get_print_file( int $id ): ?object {
		$cache_key  = 'print_file_' . $id;
		$generation = wp_cache_get_last_changed( self::PRINT_FILE_CACHE_GROUP );
		$found      = false;
		$cached     = wp_cache_get( $generation . ':' . $cache_key, self::PRINT_FILE_CACHE_GROUP, false, $found );
		if ( $found ) {
			return $cached;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE id = %d LIMIT 1",
				$id
			)
		) ?: null;
		wp_cache_set( $generation . ':' . $cache_key, $row, self::PRINT_FILE_CACHE_GROUP, self::PRINT_FILE_CACHE_TTL );
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
				if ( class_exists( 'OC_Logger', false ) ) {
					call_user_func( [ 'OC_Logger', 'warning' ], 'Print file creation deferred while the database schema is being upgraded.' );
				}
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
			$result = $wpdb->query( $wpdb->prepare( $sql, ...$values ) );
			if ( false === $result ) {
				return 0;
			}
			$id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE identity_key = %s LIMIT 1",
				$data['identity_key']
			) );
		} else {
			$result = $wpdb->insert( $table, $data );
			$id     = false === $result ? 0 : (int) $wpdb->insert_id;
		}
		if ( $id > 0 ) {
			self::invalidate_print_file_cache();
		}
		return $id;
	}

	/**
	 * Update an existing print file record.
	 * @param int   $id    Record ID.
	 * @param array $data  Column => value pairs to update.
	 */
	public static function update_print_file( int $id, array $data ): bool {
		global $wpdb;
		$result = $wpdb->update( $wpdb->prefix . 'oc_print_files', $data, [ 'id' => $id ] );
		if ( false === $result ) {
			return false;
		}

		self::invalidate_print_file_cache();
		return true;
	}

	/** Update several print-file records as one regeneration commit. */
	public static function update_print_files_atomically( array $ids, array $data ): bool {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) || empty( $data ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		try {
			foreach ( $ids as $id ) {
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}oc_print_files WHERE id = %d FOR UPDATE",
					$id
				) );
				if ( ! $exists || false === $wpdb->update( $wpdb->prefix . 'oc_print_files', $data, [ 'id' => $id ] ) ) {
					throw new \RuntimeException( 'A print file could not be updated.' );
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'The print file updates could not be committed.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		self::invalidate_print_file_cache();
		return true;
	}

	/** Commit generated file rows and their claimed queue job as one success boundary. */
	public static function complete_queue_job( int $job_id, int $attempts, array $file_updates ): bool {
		global $wpdb;
		if ( empty( $file_updates ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$file_ids = [];
		try {
			$queue = $wpdb->get_row( $wpdb->prepare(
				"SELECT status, attempts FROM {$wpdb->prefix}oc_print_queue WHERE id = %d FOR UPDATE",
				$job_id
			) );
			if ( ! $queue || 'processing' !== (string) $queue->status || $attempts !== (int) $queue->attempts ) {
				throw new \RuntimeException( 'The queue claim is no longer current.' );
			}

			foreach ( $file_updates as $update ) {
				$file_id         = (int) ( $update['id'] ?? 0 );
				$expected_status = (string) ( $update['expected_status'] ?? '' );
				$data            = is_array( $update['data'] ?? null ) ? $update['data'] : [];
				$current_status  = $wpdb->get_var( $wpdb->prepare(
					"SELECT file_status FROM {$wpdb->prefix}oc_print_files WHERE id = %d FOR UPDATE",
					$file_id
				) );
				if ( $file_id <= 0 || '' === $expected_status || $expected_status !== (string) $current_status || empty( $data ) ) {
					throw new \RuntimeException( 'A print file state changed while its queue job was running.' );
				}

				$updated = $wpdb->update(
					$wpdb->prefix . 'oc_print_files',
					$data,
					[ 'id' => $file_id, 'file_status' => $expected_status ]
				);
				if ( 1 !== $updated ) {
					throw new \RuntimeException( 'A generated print file could not be committed.' );
				}
				$file_ids[] = $file_id;
			}

			$queue_updated = $wpdb->update(
				$wpdb->prefix . 'oc_print_queue',
				[
					'status'        => 'done',
					'error_message' => null,
					'processed_at'  => current_time( 'mysql', true ),
				],
				[ 'id' => $job_id, 'status' => 'processing', 'attempts' => $attempts ]
			);
			if ( 1 !== $queue_updated || false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'The completed queue state could not be committed.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		self::invalidate_print_file_cache();
		return true;
	}

	/** Atomically move a claimed or exhausted queue job and its files to failed. */
	public static function fail_queue_job( int $job_id, string $expected_status, int $attempts, string $error_message, array $file_ids ): bool {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$file_ids = array_values( array_unique( array_filter( array_map( 'absint', $file_ids ) ) ) );
		try {
			$queue = $wpdb->get_row( $wpdb->prepare(
				"SELECT status, attempts FROM {$wpdb->prefix}oc_print_queue WHERE id = %d FOR UPDATE",
				$job_id
			) );
			if ( ! $queue || $expected_status !== (string) $queue->status || $attempts !== (int) $queue->attempts ) {
				throw new \RuntimeException( 'The queue state changed before it could be failed.' );
			}

			foreach ( $file_ids as $file_id ) {
				$current_status = $wpdb->get_var( $wpdb->prepare(
					"SELECT file_status FROM {$wpdb->prefix}oc_print_files WHERE id = %d FOR UPDATE",
					$file_id
				) );
				if ( ! in_array( (string) $current_status, [ 'pending', 'generating', 'failed' ], true ) ) {
					throw new \RuntimeException( 'A terminal queue failure would regress a completed print file.' );
				}
				if ( 'failed' !== (string) $current_status ) {
					$updated = $wpdb->update(
						$wpdb->prefix . 'oc_print_files',
						[ 'file_status' => 'failed' ],
						[ 'id' => $file_id, 'file_status' => (string) $current_status ]
					);
					if ( 1 !== $updated ) {
						throw new \RuntimeException( 'A failed print file state could not be committed.' );
					}
				}
			}

			$queue_updated = $wpdb->update(
				$wpdb->prefix . 'oc_print_queue',
				[
					'status'        => 'failed',
					'error_message' => $error_message,
					'processed_at'  => current_time( 'mysql', true ),
				],
				[ 'id' => $job_id, 'status' => $expected_status, 'attempts' => $attempts ]
			);
			if ( 1 !== $queue_updated || false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'The terminal queue failure could not be committed.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		if ( ! empty( $file_ids ) ) {
			self::invalidate_print_file_cache();
		}
		return true;
	}

	/** Reset a failed queue job and all of its failed file rows for an explicit retry. */
	public static function retry_failed_queue_job( int $job_id, int $attempts, array $file_ids ): bool {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$file_ids = array_values( array_unique( array_filter( array_map( 'absint', $file_ids ) ) ) );
		try {
			$queue = $wpdb->get_row( $wpdb->prepare(
				"SELECT status, attempts FROM {$wpdb->prefix}oc_print_queue WHERE id = %d FOR UPDATE",
				$job_id
			) );
			if ( ! $queue || 'failed' !== (string) $queue->status || $attempts !== (int) $queue->attempts ) {
				throw new \RuntimeException( 'Only the current failed queue state can be retried.' );
			}

			foreach ( $file_ids as $file_id ) {
				$current_status = $wpdb->get_var( $wpdb->prepare(
					"SELECT file_status FROM {$wpdb->prefix}oc_print_files WHERE id = %d FOR UPDATE",
					$file_id
				) );
				if ( ! in_array( (string) $current_status, [ 'failed', 'pending' ], true ) ) {
					throw new \RuntimeException( 'A completed print file cannot be reset by queue retry.' );
				}
				if ( 'pending' !== (string) $current_status ) {
					$updated = $wpdb->update(
						$wpdb->prefix . 'oc_print_files',
						[ 'file_status' => 'pending' ],
						[ 'id' => $file_id, 'file_status' => 'failed' ]
					);
					if ( 1 !== $updated ) {
						throw new \RuntimeException( 'A failed print file could not be reset.' );
					}
				}
			}

			$queue_updated = $wpdb->update(
				$wpdb->prefix . 'oc_print_queue',
				[
					'status'        => 'pending',
					'attempts'      => 0,
					'error_message' => null,
					'processed_at'  => null,
				],
				[ 'id' => $job_id, 'status' => 'failed', 'attempts' => $attempts ]
			);
			if ( 1 !== $queue_updated || false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'The queue retry could not be committed.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		if ( ! empty( $file_ids ) ) {
			self::invalidate_print_file_cache();
		}
		return true;
	}

	/** Mark newly-created file rows failed when their queue payload cannot be persisted. */
	public static function fail_unqueued_print_files( array $file_ids ): bool {
		global $wpdb;
		$file_ids = array_values( array_unique( array_filter( array_map( 'absint', $file_ids ) ) ) );
		if ( empty( $file_ids ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		try {
			foreach ( $file_ids as $file_id ) {
				$current_status = $wpdb->get_var( $wpdb->prepare(
					"SELECT file_status FROM {$wpdb->prefix}oc_print_files WHERE id = %d FOR UPDATE",
					$file_id
				) );
				if ( ! in_array( (string) $current_status, [ 'pending', 'generating', 'failed' ], true ) ) {
					throw new \RuntimeException( 'An unqueued failure would regress a completed print file.' );
				}
				if ( 'failed' !== (string) $current_status ) {
					$updated = $wpdb->update(
						$wpdb->prefix . 'oc_print_files',
						[ 'file_status' => 'failed' ],
						[ 'id' => $file_id, 'file_status' => (string) $current_status ]
					);
					if ( 1 !== $updated ) {
						throw new \RuntimeException( 'An unqueued print file could not be failed.' );
					}
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'The unqueued print file failure could not be committed.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		self::invalidate_print_file_cache();
		return true;
	}

	/** Advance the print-file cache generation after a committed write. */
	private static function invalidate_print_file_cache(): void {
		wp_cache_set_last_changed( self::PRINT_FILE_CACHE_GROUP );
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
	public static function get_pending_queue_jobs( int $limit = 5, int $max_attempts = 3 ): array {
		global $wpdb;
		$now = current_time( 'mysql', true );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_queue
				 WHERE status = 'pending'
				 AND attempts < %d
				 AND (processed_at IS NULL OR processed_at <= %s)
				 ORDER BY created_at ASC, id ASC
				 LIMIT %d",
				$max_attempts,
				$now,
				$limit
			)
		) ?: [];
	}

	/** Return whether another claimable queue job is currently due. */
	public static function has_due_queue_jobs( int $max_attempts = 3 ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}oc_print_queue
			 WHERE status = 'pending' AND attempts < %d
			 AND (processed_at IS NULL OR processed_at <= %s)
			 ORDER BY created_at ASC, id ASC LIMIT 1",
			$max_attempts,
			current_time( 'mysql', true )
		) );
	}

	/** Make a completed checkout batch immediately claimable without touching retries. */
	public static function release_deferred_queue_jobs( array $job_ids ): int {
		global $wpdb;
		$job_ids = array_values( array_unique( array_filter( array_map( 'absint', $job_ids ) ) ) );
		if ( empty( $job_ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}oc_print_queue SET processed_at = NULL
			 WHERE id IN ({$placeholders}) AND status = 'pending' AND attempts = 0",
			...$job_ids
		) );

		return false === $result ? 0 : (int) $result;
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

	/** Compare-and-set a queue state, optionally tied to the current claim attempt. */
	public static function transition_queue_job( int $id, string $expected_status, array $data, ?int $attempts = null ): bool {
		global $wpdb;

		$where = [ 'id' => $id, 'status' => $expected_status ];
		if ( null !== $attempts ) {
			$where['attempts'] = $attempts;
		}

		return 1 === $wpdb->update( $wpdb->prefix . 'oc_print_queue', $data, $where );
	}

	/** Return active, partial_failure, or complete for an order's persisted pipeline state. */
	public static function get_order_print_pipeline_state( int $order_id ): string {
		global $wpdb;

		$queue = $wpdb->get_row( $wpdb->prepare(
			"SELECT
			 SUM(status IN ('pending','processing')) AS active_jobs,
			 SUM(status = 'failed') AS failed_jobs
			 FROM {$wpdb->prefix}oc_print_queue WHERE order_id = %d",
			$order_id
		) );
		$files = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total_files,
			 SUM(file_status NOT IN ('brief_ready','awaiting_dst_upload','files_ready')) AS non_ready_files
			 FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d",
			$order_id
		) );

		if ( ! $queue || ! $files || (int) $queue->active_jobs > 0 ) {
			return 'active';
		}

		if ( (int) $queue->failed_jobs > 0 || (int) $files->total_files < 1 || (int) $files->non_ready_files > 0 ) {
			return 'partial_failure';
		}

		return 'complete';
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
