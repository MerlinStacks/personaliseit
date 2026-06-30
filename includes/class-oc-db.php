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
			PRIMARY KEY (id)
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
			active     TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
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
			mockup_attachment_id BIGINT UNSIGNED DEFAULT NULL,
			canvas_x             INT NOT NULL DEFAULT 0,
			canvas_y             INT NOT NULL DEFAULT 0,
			canvas_w             INT NOT NULL DEFAULT 300,
			canvas_h             INT NOT NULL DEFAULT 300,
			canvas_rotation      INT NOT NULL DEFAULT 0,
			sort_order           INT NOT NULL DEFAULT 0,
			visible              TINYINT(1) NOT NULL DEFAULT 1,
			locked               TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY          design_id (design_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 14. Product design assignments (product/variant → design)
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_product_assignments (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			design_id  BIGINT UNSIGNED NOT NULL,
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
			KEY          area_id (area_id)
		) $charset;" );

		// ------------------------------------------------------------------
		// 16. Generated print files per order item
		// ------------------------------------------------------------------
		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_print_files (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id       BIGINT UNSIGNED NOT NULL,
			order_item_id  BIGINT UNSIGNED NOT NULL,
			print_area_id  BIGINT UNSIGNED NOT NULL,
			file_type      VARCHAR(20)  NOT NULL DEFAULT '',
			file_path      VARCHAR(500) DEFAULT NULL,
			thumbnail_path VARCHAR(500) DEFAULT NULL,
			file_status    ENUM('pending','generating','brief_ready','awaiting_dst_upload','files_ready','expired') NOT NULL DEFAULT 'pending',
			generated_at   DATETIME DEFAULT NULL,
			expires_at     DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
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
			order_id       BIGINT UNSIGNED NOT NULL,
			order_item_id  BIGINT UNSIGNED NOT NULL,
			print_area_id  BIGINT UNSIGNED NOT NULL,
			area_data      TEXT NOT NULL,
			print_method   VARCHAR(20) NOT NULL DEFAULT '',
			status         ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
			attempts       INT NOT NULL DEFAULT 0,
			error_message  TEXT DEFAULT NULL,
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at   DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY order_item_id (order_item_id)
		) $charset;" );
	}

	/** Run migrations if DB version is outdated. */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'oc_db_version', '0' );

		if ( version_compare( $installed, OC_DB_VERSION, '<' ) ) {
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

			update_option( 'oc_db_version', OC_DB_VERSION );
		}
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
		$cache_key = 'fonts_' . ( $active_only ? 'active' : 'all' );
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
		$wpdb->insert( $wpdb->prefix . 'oc_print_files', $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing print file record.
	 * @param int   $id    Record ID.
	 * @param array $data  Column => value pairs to update.
	 */
	public static function update_print_file( int $id, array $data ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'oc_print_files', $data, [ 'id' => $id ] );
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
					"SELECT * FROM {$wpdb->prefix}oc_designs WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}oc_designs ORDER BY name ASC"
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
			 ORDER BY d.name ASC"
		) ?: [];
		OC_Cache::set( $cache_key, $results, OC_Cache::TTL_SHORT );
		return $results;
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
			"SELECT product_id, variant_id, design_id FROM {$wpdb->prefix}oc_product_assignments"
		) ?: [];

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->product_id ][ (int) $row->variant_id ] = [
				'design_id' => (int) $row->design_id,
			];
		}
		OC_Cache::set( $cache_key, $map );
		return $map;
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
	public static function upsert_vdp_template( array $data ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}oc_vdp_templates (design_id, csv_file_path, active)
			 VALUES (%d, %s, %d)
			 ON DUPLICATE KEY UPDATE csv_file_path = VALUES(csv_file_path), active = VALUES(active)",
			$data['design_id'],
			$data['csv_file_path'] ?? null,
			$data['active'] ?? 0
		) );
	}

	/** Delete a VDP template and its fields. */
	public static function delete_vdp_template( int $design_id ): void {
		global $wpdb;
		$template = self::get_vdp_template( $design_id );
		if ( $template ) {
			self::delete_vdp_fields( (int) $template->id );
		}
		$wpdb->delete(
			"{$wpdb->prefix}oc_vdp_templates",
			[ 'design_id' => $design_id ],
			[ '%d' ]
		);
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
	public static function delete_vdp_fields( int $template_id ): void {
		global $wpdb;
		$wpdb->delete(
			"{$wpdb->prefix}oc_vdp_fields",
			[ 'template_id' => $template_id ],
			[ '%d' ]
		);
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
