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
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id      BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			print_area_id BIGINT UNSIGNED NOT NULL,
			file_type     VARCHAR(20)  NOT NULL DEFAULT '',
			file_path     VARCHAR(500) DEFAULT NULL,
			file_status   ENUM('pending','generating','brief_ready','awaiting_dst_upload','files_ready','expired') NOT NULL DEFAULT 'pending',
			generated_at  DATETIME DEFAULT NULL,
			expires_at    DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY order_id      (order_id),
			KEY order_item_id (order_item_id),
			KEY file_status   (file_status),
			KEY expires_at    (expires_at)
		) $charset;" );
	}

	/** Run migrations if DB version is outdated. */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'oc_db_version', '0' );

		if ( version_compare( $installed, OC_DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'oc_db_version', OC_DB_VERSION );
		}
	}

	// -------------------------------------------------------------------------
	// Helper query methods
	// -------------------------------------------------------------------------

	/** Fetch a product config row by WC product ID. */
	public static function get_config_by_product( int $product_id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_product_configs WHERE product_id = %d LIMIT 1",
				$product_id
			)
		) ?: null;
	}

	/** Fetch all print areas for a given config ID. */
	public static function get_print_areas( int $config_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_areas WHERE config_id = %d ORDER BY sort_order ASC",
				$config_id
			)
		) ?: [];
	}

	/** Fetch all colours (optionally active-only). */
	public static function get_colours( bool $active_only = true ): array {
		global $wpdb;
		if ( $active_only ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_colours WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		}
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_colours ORDER BY name ASC"
		) ?: [];
	}

	/** Fetch all colour groups with their associated colour IDs. */
	public static function get_colour_groups(): array {
		global $wpdb;

		$groups = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_colour_groups ORDER BY name ASC"
		) ?: [];

		if ( empty( $groups ) ) {
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

		return $groups;
	}

	/** Fetch all font groups with their associated font IDs. */
	public static function get_font_groups(): array {
		global $wpdb;

		$groups = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_font_groups ORDER BY name ASC"
		) ?: [];

		if ( empty( $groups ) ) {
			return [];
		}

		// Fetch all items in one query and map by group_id.
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

		return $groups;
	}

	/** Fetch all active fonts. */
	public static function get_fonts( bool $active_only = true ): array {
		global $wpdb;
		if ( $active_only ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		}
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_fonts ORDER BY name ASC"
		) ?: [];
	}

	/** Fetch print files for a given order item. */
	public static function get_print_files_for_item( int $order_item_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_item_id = %d",
				$order_item_id
			)
		) ?: [];
	}

	/** Fetch a single print file record by ID. */
	public static function get_print_file( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE id = %d LIMIT 1",
				$id
			)
		) ?: null;
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
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_design_layers
			 WHERE design_id = %d
			 ORDER BY area_id ASC, sort_order ASC",
			$design_id
		) ) ?: [];
	}

	/** Fetch all clipart (optionally active-only). */
	public static function get_clipart( bool $active_only = true ): array {
		global $wpdb;
		if ( $active_only ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_clipart WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		}
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_clipart ORDER BY name ASC"
		) ?: [];
	}

	/** Fetch all clipart groups with their clipart IDs. */
	public static function get_clipart_groups(): array {
		global $wpdb;

		$groups = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_clipart_groups ORDER BY name ASC"
		) ?: [];

		if ( empty( $groups ) ) {
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

		return $groups;
	}

	// ── Designs ───────────────────────────────────────────────────────────────

	/** Fetch a single design by ID. */
	public static function get_design( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_designs WHERE id = %d LIMIT 1", $id
		) ) ?: null;
	}

	/** Fetch all designs (optionally active-only). */
	public static function get_designs( bool $active_only = false ): array {
		global $wpdb;
		if ( $active_only ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_designs WHERE active = %d ORDER BY name ASC",
					1
				)
			) ?: [];
		}
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_designs ORDER BY name ASC"
		) ?: [];
	}

	/** Fetch all designs with their print area counts. */
	public static function get_designs_with_area_counts(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT d.*, COUNT(a.id) AS area_count
			 FROM {$wpdb->prefix}oc_designs d
			 LEFT JOIN {$wpdb->prefix}oc_design_print_areas a ON a.design_id = d.id
			 GROUP BY d.id
			 ORDER BY d.name ASC"
		) ?: [];
	}

	/** Fetch print areas for a design. */
	public static function get_design_print_areas( int $design_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id = %d ORDER BY sort_order ASC",
			$design_id
		) ) ?: [];
	}

	// ── Product assignments ───────────────────────────────────────────────────

	/** Fetch all product design assignments keyed as [product_id][variant_id] => design_id. */
	public static function get_all_assignments(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT product_id, variant_id, design_id FROM {$wpdb->prefix}oc_product_assignments"
		) ?: [];

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->product_id ][ (int) $row->variant_id ] = (int) $row->design_id;
		}
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
	}

	/** Remove a product/variant assignment (unassign design). */
	public static function delete_assignment( int $product_id, int $variant_id ): void {
		global $wpdb;
		$wpdb->delete(
			"{$wpdb->prefix}oc_product_assignments",
			[ 'product_id' => $product_id, 'variant_id' => $variant_id ],
			[ '%d', '%d' ]
		);
	}
}
