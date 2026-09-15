<?php
/**
 * VDP CSV upload, private storage, and migration for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API; also supplies canonical path checks to previews. */
trait OC_Rest_API_VDP {

	/** Ensure VDP values use private storage and migrate a bounded legacy batch. */
	public static function ensure_vdp_storage(): void {
		$legacy_directory = self::legacy_vdp_directory();
		if ( null !== $legacy_directory ) {
			self::protect_legacy_vdp_directory( $legacy_directory );
		}

		$directory = self::protected_vdp_directory();
		if ( null === $directory ) {
			OC_Logger::warning( 'Private VDP storage is unavailable.' );
			return;
		}

		self::migrate_legacy_vdp_files( $directory );
		self::migrate_private_vdp_files( $directory );
	}

	/** Upload and register a CSV file for VDP on a design. */
	public function upload_vdp_csv( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_header( 'X-OC-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$design_id = absint( $request->get_param( 'design_id' ) );
		if ( ! $design_id ) {
			return new \WP_Error( 'invalid_param', __( 'design_id required.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$design = OC_DB::get_design( $design_id );
		if ( ! $design || ! (bool) $design->active ) {
			return new \WP_Error( 'invalid_design', __( 'Design not found or inactive.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		// Check before private storage creation or staging the uploaded file.
		if ( ! OC_DB::tables_support_transactions( [ 'oc_designs', 'oc_design_layers', 'oc_vdp_templates', 'oc_vdp_fields' ] ) ) {
			return new \WP_Error( 'transaction_unavailable', __( 'VDP updates require InnoDB tables. No changes were applied. Contact the site administrator.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$files = $request->get_file_params();
		if ( empty( $files['csv'] ) || ! is_uploaded_file( $files['csv']['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No CSV file received.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( UPLOAD_ERR_OK !== (int) $files['csv']['error'] ) {
			return new \WP_Error( 'upload_failed', __( 'CSV upload failed.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		$ext = strtolower( pathinfo( $files['csv']['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return new \WP_Error( 'invalid_type', __( 'Only CSV files are allowed.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$max_bytes     = 5 * 1024 * 1024;
		$actual_size   = filesize( $files['csv']['tmp_name'] );
		$reported_size = (int) ( $files['csv']['size'] ?? 0 );
		if ( false === $actual_size || $actual_size <= 0 || $reported_size <= 0 || $reported_size !== (int) $actual_size ) {
			return new \WP_Error( 'invalid_csv', __( 'The CSV file is empty or unreadable.', 'overcustomise' ), [ 'status' => 422 ] );
		}
		if ( $actual_size > $max_bytes ) {
			return new \WP_Error( 'too_large', __( 'CSV file exceeds 5 MB.', 'overcustomise' ), [ 'status' => 413 ] );
		}

		$dir = self::protected_vdp_directory();
		if ( null === $dir ) {
			OC_Logger::error( 'Private VDP storage was unavailable during an upload.' );
			return new \WP_Error( 'storage_protection_failed', __( 'Private VDP storage is unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$filename = 'vdp-' . wp_generate_uuid4() . '.csv';
		$filepath = $dir . '/' . $filename;

		if ( false === move_uploaded_file( $files['csv']['tmp_name'], $filepath ) ) {
			return new \WP_Error( 'save_failed', __( 'Could not save CSV file.', 'overcustomise' ), [ 'status' => 500 ] );
		}
		if ( filesize( $filepath ) !== $actual_size || ! @chmod( $filepath, 0640 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::error( 'A staged VDP upload failed its private file verification.' );
			return new \WP_Error( 'save_failed', __( 'Could not save CSV file.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$old_filepath = '';
		$vdp          = new OC_VDP();
		$csv_data     = $vdp->parse_csv( $filepath );

		if ( ! empty( $csv_data['error'] ) || empty( $csv_data['headers'] ) || empty( $csv_data['rows'] ) ) {
			@unlink( $filepath );
			return new \WP_Error( 'invalid_csv', (string) ( $csv_data['error'] ?? __( 'CSV must contain at least one data row.', 'overcustomise' ) ), [ 'status' => 422 ] );
		}

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::delete_vdp_file( $filepath );
			return new \WP_Error( 'db_error', __( 'Could not start the VDP update.', 'overcustomise' ), [ 'status' => 500 ] );
		}
		$validation_error = null;
		try {
			// Serialize replacements for this design, including its first template.
			$locked_design = $wpdb->get_row( $wpdb->prepare( "SELECT id, active FROM {$wpdb->prefix}oc_designs WHERE id = %d FOR UPDATE", $design_id ) );
			$design_error = (string) $wpdb->last_error;
			if ( ! $locked_design || '' !== $design_error ) {
				throw new \RuntimeException( 'Could not lock the VDP design.' );
			}
			if ( ! (bool) $locked_design->active ) {
				$validation_error = new \WP_Error( 'invalid_design', __( 'Design not found or inactive.', 'overcustomise' ), [ 'status' => 400 ] );
				throw new \RuntimeException( 'The VDP design is inactive.' );
			}
			// Bypass cached layers and use current locking reads, including settings and order.
			$all_layers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}oc_design_layers WHERE design_id = %d ORDER BY area_id ASC, sort_order ASC, id ASC FOR UPDATE", $design_id ) );
			$layers_error = (string) $wpdb->last_error;
			if ( ! is_array( $all_layers ) || '' !== $layers_error ) {
				throw new \RuntimeException( 'Could not lock the VDP layers.' );
			}
			$all_layers = array_values( array_filter(
				$all_layers,
				static fn( object $layer ): bool => (bool) $layer->visible && empty( $layer->locked ) && in_array( (string) $layer->type, [ 'text', 'textarea', 'spotify' ], true )
			) );
			if ( count( $csv_data['headers'] ) > count( $all_layers ) ) {
				$validation_error = new \WP_Error( 'invalid_csv_fields', __( 'The CSV contains more fields than the design has editable variable layers.', 'overcustomise' ), [ 'status' => 422 ] );
				throw new \RuntimeException( 'The VDP design has insufficient editable layers.' );
			}
			foreach ( $csv_data['headers'] as $index => $header ) {
				foreach ( $csv_data['rows'] as $row ) {
					$value = $vdp->normalise_layer_value( $all_layers[ $index ], (string) ( $row[ $header ] ?? '' ) );
					if ( is_wp_error( $value ) ) {
						$validation_error = new \WP_Error( 'invalid_csv_value', $value->get_error_message(), [ 'status' => 422 ] );
						throw new \RuntimeException( 'The CSV values do not match the current VDP layers.' );
					}
				}
			}
			$layer_ids = array_values( array_map( static fn( object $layer ): int => (int) $layer->id, $all_layers ) );
			$old_template = OC_DB::get_vdp_template( $design_id );
			$old_filepath = $old_template ? (string) $old_template->csv_file_path : '';
			if ( ! OC_DB::delete_vdp_template( $design_id ) ) {
				throw new \RuntimeException( 'Could not remove the previous VDP template.' );
			}
			if ( ! OC_DB::upsert_vdp_template(
				[
					'design_id'     => $design_id,
					'csv_file_path' => $filepath,
					'active'        => 1,
				]
			) ) {
				throw new \RuntimeException( 'Could not create the VDP template.' );
			}

			$template = OC_DB::get_vdp_template( $design_id );
			if ( ! $template ) {
				throw new \RuntimeException( 'Could not reload the VDP template.' );
			}

			foreach ( $csv_data['headers'] as $index => $header ) {
				$inserted = OC_DB::insert_vdp_field(
					[
						'template_id' => (int) $template->id,
						'field_name'  => $header,
						'layer_id'    => $layer_ids[ $index ] ?? 0,
						'sort_order'  => $index,
					]
				);
				if ( $inserted <= 0 ) {
					throw new \RuntimeException( 'Could not save VDP fields.' );
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'Could not commit the VDP template.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			self::delete_vdp_file( $filepath );
			OC_Logger::error( 'VDP replacement failed: ' . $e->getMessage() );
			return $validation_error ?? new \WP_Error( 'db_error', __( 'Could not save the VDP template.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		if ( '' !== $old_filepath && $old_filepath !== $filepath ) {
			self::delete_vdp_file( $old_filepath );
		}

		return rest_ensure_response(
			[
				'success'     => true,
				'template_id' => (int) $template->id,
				'fields'      => $csv_data['headers'],
				'row_count'   => count( $csv_data['rows'] ),
				'file_name'   => sanitize_file_name( basename( (string) $files['csv']['name'] ) ) ?: 'data.csv',
			]
		);
	}

	/** Return the VDP directory outside public uploads. */
	private static function protected_vdp_directory(): ?string {
		return OC_Upload_Handler::private_storage_path( 'vdp' );
	}

	/** Advance through private-root candidates without allowing a bad row to starve later IDs. */
	private static function migrate_private_vdp_files( string $directory ): void {
		global $wpdb;
		$cursor = max( 0, (int) get_option( 'oc_private_vdp_migration_cursor', 0 ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}oc_vdp_templates WHERE id > %d AND csv_file_path NOT LIKE %s ORDER BY id ASC LIMIT 25",
			$cursor, $wpdb->esc_like( $directory . '/' ) . '%'
		) );
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			OC_Storage_Upgrade::report( 'vdp', 'Private VDP migration could not read its batch; files and rows retained.' );
			return;
		}
		$deadline = microtime( true ) + 2;
		$processed = 0;
		foreach ( $rows as $row ) {
			if ( $processed > 0 && microtime( true ) >= $deadline ) {
				break;
			}
			update_option( 'oc_private_vdp_migration_cursor', (int) $row->id, false );
			self::relocate_private_vdp_template( (int) $row->id );
			$processed++;
		}
		if ( $processed === count( $rows ) && count( $rows ) < 25 ) {
			update_option( 'oc_private_vdp_migration_cursor', 0, false );
		}
	}

	/** Storage-only migration API: never grants design, order or public CSV access. */
	public static function relocate_private_vdp_template( int $template_id ): ?string {
		if ( $template_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT id, design_id, csv_file_path FROM {$wpdb->prefix}oc_vdp_templates WHERE id = %d", $template_id ) );
		if ( ! $record || '' !== (string) $wpdb->last_error ) {
			OC_Storage_Upgrade::report( 'vdp:' . $template_id, 'VDP row is missing or unreadable; no relocation performed.' );
			return null;
		}
		$directory = self::protected_vdp_directory();
		if ( null === $directory ) {
			OC_Storage_Upgrade::report( 'vdp:' . $template_id, 'VDP relocation blocked: verified destination unavailable; source and row retained.' );
			return null;
		}
		$original = (string) $record->csv_file_path;
		$source = OC_Storage_Upgrade::canonical_file( $original );
		if ( null !== $source && self::path_is_within( $source, $directory ) ) {
			return $source;
		}
		$source = OC_Storage_Upgrade::known_private_file( $original, 'vdp' );
		if ( null === $source || 'csv' !== strtolower( pathinfo( $source, PATHINFO_EXTENSION ) ) ) {
			OC_Storage_Upgrade::report( 'vdp:' . $template_id, 'VDP source is missing or outside exact known prior private VDP roots; row retained for review.' );
			return null;
		}
		$bytes = file_get_contents( $source, false, null, 0, 5 * 1024 * 1024 + 1 );
		if ( ! is_string( $bytes ) || '' === $bytes || strlen( $bytes ) > 5 * 1024 * 1024 ) {
			OC_Storage_Upgrade::report( 'vdp:' . $template_id, 'Private VDP source exceeds its 5 MiB relocation limit or is unreadable; retained for review.' );
			return null;
		}
		$hash = hash( 'sha256', $bytes );
		unset( $bytes );
		// Retry the same immutable copy after an uncertain commit, rather than leaking one copy per request.
		$name = 'vdp-relocated-' . substr( hash( 'sha256', get_current_blog_id() . '|' . $template_id . '|' . (int) $record->design_id . '|' . $source . '|' . $hash ), 0, 40 ) . '.csv';
		$destination = OC_Storage_Upgrade::copy_known_private_file( $source, 'vdp', $name, 5 * 1024 * 1024, $hash );
		if ( null === $destination ) {
			return null;
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}oc_vdp_templates SET csv_file_path = %s WHERE id = %d AND design_id = %d AND csv_file_path = %s",
				$destination,
				$template_id,
				(int) $record->design_id,
				$original
			)
		);
		if ( 1 !== $updated ) {
			// An uncertain commit must not result in deleting a possibly published destination.
			OC_Storage_Upgrade::report( 'vdp:' . $template_id, 'VDP pointer publication raced or failed; source and copied destination retained for reference-safe reconciliation.' );
			return null;
		}
		OC_Storage_Upgrade::report( 'vdp:' . $template_id, 'VDP path relocated without changing template fields/design; source retained pending reference-safe cleanup.' );
		return $destination;
	}

	/** Migrate at most 25 legacy public-upload VDP files per request. */
	private static function migrate_legacy_vdp_files( string $private_directory ): void {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}
		$legacy_directory = trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/vdp';
		$legacy_real      = self::legacy_vdp_directory();
		if ( null === $legacy_real ) {
			return;
		}
		self::protect_legacy_vdp_directory( $legacy_real );

		global $wpdb;
		$cursor = max( 0, (int) get_option( 'oc_vdp_migration_cursor', 0 ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, csv_file_path FROM {$wpdb->prefix}oc_vdp_templates WHERE csv_file_path LIKE %s AND id > %d ORDER BY id ASC LIMIT 25",
				$wpdb->esc_like( rtrim( wp_normalize_path( $legacy_directory ), '/' ) ) . '/%',
				$cursor
			)
		);
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return;
		}
		$last = $rows ? end( $rows ) : null;
		update_option( 'oc_vdp_migration_cursor', count( $rows ) < 25 ? 0 : (int) $last->id, false );
		foreach ( $rows as $row ) {
			$source = realpath( (string) $row->csv_file_path );
			if ( false === $source || ! is_file( $source ) || ! self::path_is_within( $source, $legacy_real ) ) {
				OC_Logger::warning( 'A legacy VDP migration row referenced an unavailable or unsafe file.' );
				continue;
			}
			$destination = $private_directory . '/vdp-' . wp_generate_uuid4() . '.csv';
			if ( ! self::atomic_private_copy( $source, $destination ) ) {
				OC_Logger::warning( 'A legacy VDP file could not be copied into private storage.' );
				continue;
			}
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}oc_vdp_templates SET csv_file_path = %s WHERE id = %d AND csv_file_path = %s",
					$destination,
					(int) $row->id,
					(string) $row->csv_file_path
				)
			);
			if ( 1 !== $updated ) {
				@unlink( $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				OC_Logger::warning( 'A legacy VDP migration lost its database update race.' );
				continue;
			}
			wp_delete_file( $source );
		}
	}

	/** Resolve the exact legacy VDP root only when it stays inside uploads. */
	private static function legacy_vdp_directory(): ?string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		$uploads_real = realpath( (string) $uploads['basedir'] );
		$legacy_real  = realpath( trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/vdp' );
		if ( false === $uploads_real || false === $legacy_real || ! is_dir( $legacy_real ) || ! self::path_is_within( $legacy_real, $uploads_real ) ) {
			return null;
		}
		return $legacy_real;
	}

	/** Install best-effort deny rules while bounded VDP migration is in progress. */
	private static function protect_legacy_vdp_directory( string $directory ): bool {
		$files = [
			'.htaccess'  => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php'  => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$path = $directory . '/' . $filename;
			if ( is_file( $path ) && hash_equals( $contents, (string) file_get_contents( $path ) ) ) {
				continue;
			}
			if ( ! self::atomic_replace_file( $path, $contents, 0640 ) ) {
				OC_Logger::warning( 'Legacy VDP deny rules could not be installed while migration is pending.' );
				return false;
			}
		}
		return true;
	}

	/** Delete a VDP CSV only from the private or exact legacy VDP root. */
	public static function delete_vdp_file( string $filepath ): void {
		$real = realpath( $filepath );
		if ( false === $real || ! is_file( $real ) ) {
			return;
		}
		$private = self::protected_vdp_directory();
		if ( null !== $private && self::path_is_within( $real, $private ) ) {
			wp_delete_file( $real );
			return;
		}

		$uploads      = wp_upload_dir();
		$uploads_real = empty( $uploads['error'] ) ? realpath( (string) ( $uploads['basedir'] ?? '' ) ) : false;
		$legacy       = false !== $uploads_real ? realpath( $uploads_real . '/overcustomise/vdp' ) : false;
		if ( false !== $uploads_real && false !== $legacy && self::path_is_within( $legacy, $uploads_real ) && self::path_is_within( $real, $legacy ) ) {
			wp_delete_file( $real );
		}
	}

	/** Atomically copy an existing file into private storage. */
	private static function atomic_private_copy( string $source, string $destination ): bool {
		if ( ! is_file( $source ) || is_file( $destination ) ) {
			return false;
		}
		$tmp = $destination . '.part-' . wp_generate_uuid4();
		$ok  = false;
		try {
			$source_size = filesize( $source );
			$ok          = false !== $source_size && $source_size > 0
				&& copy( $source, $tmp )
				&& filesize( $tmp ) === $source_size
				&& @chmod( $tmp, 0640 ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				&& @rename( $tmp, $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			if ( is_file( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return $ok;
	}

	/** Atomically replace a small deny-rule file. */
	private static function atomic_replace_file( string $path, string $contents, int $mode ): bool {
		$tmp = dirname( $path ) . '/.' . basename( $path ) . '.part-' . wp_generate_uuid4();
		$ok  = false;
		try {
			$ok = strlen( $contents ) === file_put_contents( $tmp, $contents, LOCK_EX )
				&& @chmod( $tmp, $mode ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				&& @rename( $tmp, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			if ( is_file( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return $ok;
	}

	/** Compare canonical paths without permitting prefix collisions. */
	private static function path_is_within( string $path, string $base ): bool {
		$path = rtrim( wp_normalize_path( $path ), '/' );
		$base = rtrim( wp_normalize_path( $base ), '/' );
		if ( '' === $path || '' === $base ) {
			return false;
		}
		if ( str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' ) ) {
			$path = strtolower( $path );
			$base = strtolower( $base );
		}
		return str_starts_with( $path, $base . '/' );
	}
}
