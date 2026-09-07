<?php
/**
 * Runtime dependency checks for production workflows.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_System_Status {
	private const READINESS_CACHE = 'oc_compatibility_readiness_v2';
	private const READINESS_TTL = 300;

	/** Read cached diagnostics only. Null means unknown, not a blanket instruction to pause. */
	public static function cached_readiness_report(): ?array {
		$cached = get_transient( self::READINESS_CACHE );
		$now = time();
		if ( ! is_array( $cached ) || ! is_int( $cached['checked_at'] ?? null )
			|| $cached['checked_at'] <= 0 || $cached['checked_at'] > $now || $cached['checked_at'] <= $now - self::READINESS_TTL
			|| ( $cached['recheck_after'] ?? null ) !== self::READINESS_TTL
			|| ( $cached['target_version'] ?? '' ) !== OC_DB_VERSION
			|| ( $cached['schema_version'] ?? '' ) !== (string) get_option( 'oc_db_version', '0' ) ) {
			return null;
		}
		return $cached;
	}

	/** Admin diagnostic builder: cache misses may probe HTTP/storage and create directories. */
	public static function readiness_report( bool $refresh = false ): array {
		if ( ! $refresh ) {
			$cached = self::cached_readiness_report();
			if ( null !== $cached ) {
				return $cached;
			}
		}
		$installed = (string) get_option( 'oc_db_version', '0' );

		$resources = [
			'print' => [ 'oc_print_files', 'oc_print_queue' ],
			'designs' => [ 'oc_designs', 'oc_design_print_areas', 'oc_design_layers', 'oc_product_assignments', 'oc_vdp_templates', 'oc_vdp_fields' ],
			'fonts' => [ 'oc_font_groups', 'oc_font_group_items' ],
			'colours' => [ 'oc_colours', 'oc_colour_groups', 'oc_colour_group_items' ],
			'clipart' => [ 'oc_clipart_groups', 'oc_clipart_group_items' ],
			'tokens' => [ 'options' ],
		];
		$tables = OC_DB::transaction_readiness( array_merge( ...array_values( $resources ) ) );
		$report = [
			'checked_at' => time(),
			'recheck_after' => self::READINESS_TTL,
			'schema_version' => $installed,
			'target_version' => OC_DB_VERSION,
			'schema' => OC_DB::schema_readiness(),
			'print_schema' => OC_DB::schema_readiness( true ),
			'migration' => OC_DB::migration_readiness(),
			'tables' => $tables,
			'resources' => [],
			'storage' => [],
			'storage_upgrade' => [],
		];
		foreach ( $resources as $resource => $suffixes ) {
			$report['resources'][ $resource ] = ! array_diff( array_intersect_key( $tables, array_flip( $suffixes ) ), [ 'ready' ] );
		}
		foreach ( [ 'artwork', 'previews', 'vdp', 'print-files' ] as $directory ) {
			// The storage API can create/protect directories, but performs no legacy migration here.
			$report['storage'][ $directory ] = null !== OC_Upload_Handler::private_storage_path( $directory, true ) ? 'ready' : 'storage_blocked';
		}
		// Reports are request-local diagnostics, not authorization. Never retain root keys or raw text.
		foreach ( OC_Storage_Upgrade::reports() as $message ) {
			$code = match ( $message ) {
				'Private root overlaps the document root.' => 'storage_root_overlap',
				'No live HTTP-validated private-root evidence. Visit the site over HTTP or configure a verified operator root.' => 'storage_evidence_missing',
				'Automatic HTTP verification disabled; exact-root operator verification required.',
				'Automatic verification requires a reachable HTTPS uploads URL; configure exact-root operator verification.',
				'HTTP denial could not be verified; check origin/CDN rules and loopback access, or configure exact-root operator verification.' => 'storage_http_verification_blocked',
				'HTTP verification deferred by the per-request probe budget.',
				'Cannot lock HTTP storage verification.',
				'HTTP storage verification is already running.' => 'storage_http_verification_deferred',
				'HTTPS uploads-route denial verified (not proof of unknown aliases or external mirrors).' => 'storage_http_verified',
				'Relocation blocked: verified current private storage is unavailable; source retained.',
				'Preview relocation/read blocked: current verified storage unavailable; metadata and source retained.',
				'VDP relocation blocked: verified destination unavailable; source and row retained.' => 'relocation_storage_blocked',
				'Relocation source is empty, unreadable or exceeds the bounded copy limit.',
				'Relocation source hash did not match; source retained.',
				'Preview unavailable or changed during relocation; existing metadata and source retained.',
				'VDP row is missing or unreadable; no relocation performed.',
				'VDP source is missing or outside exact known prior private VDP roots; row retained for review.',
				'Private VDP source exceeds its 5 MiB relocation limit or is unreadable; retained for review.' => 'relocation_source_review',
				'Relocation copy verification failed; source retained.',
				'Atomic no-overwrite relocation publication failed; source retained.',
				'VDP pointer publication raced or failed; source and copied destination retained for reference-safe reconciliation.',
				'Private VDP migration could not read its batch; files and rows retained.' => 'relocation_publication_blocked',
				'Private copy published; old source retained pending reference-safe cleanup and any required HTTP denial/purge.',
				'VDP path relocated without changing template fields/design; source retained pending reference-safe cleanup.' => 'relocation_source_retained',
				default => 'storage_diagnostic_unknown',
			};
			$report['storage_upgrade'][ $code ] = 'storage_http_verified' === $code ? 'ready' : $code;
		}
		// Schema is plugin-wide diagnostic evidence; unrelated resource failures do not pause print.
		$report['print_retry_pause'] = 'ready' !== $report['print_schema'] || 'ready' !== $report['migration'] || ! $report['resources']['print'] || 'ready' !== $report['storage']['print-files'];
		set_transient( self::READINESS_CACHE, $report, self::READINESS_TTL );
		return $report;
	}

	/** Explain stable diagnostic codes without retaining database errors or filesystem paths. */
	private static function readiness_guidance( string $status ): string {
		return match ( $status ) {
			'ready' => __( 'Verified at the last check. Runtime guards still apply.', 'overcustomise' ),
			'unsupported_engine' => __( 'Back up the affected table and ask your DBA to plan an InnoDB migration in a maintenance window. Verified transactional XtraDB is also supported. No automatic conversion is performed.', 'overcustomise' ),
			'metadata_query_failed' => __( 'Database metadata or lock inspection failed. Ask your host to check database availability and INFORMATION_SCHEMA/advisory-lock access, then recheck. This does not prove the engine is unsupported.', 'overcustomise' ),
			'table_metadata_missing', 'schema_incomplete' => __( 'Required table metadata, columns or indexes are missing or incompatible. Back up and have your administrator inspect the installed schema and upgrade logs; do not change the version option to bypass checks.', 'overcustomise' ),
			'migration_required' => __( 'The installed database version is behind this plugin. Allow the normal upgrade to complete and inspect OverCustomise logs if it remains pending. Do not manually advance the version option.', 'overcustomise' ),
			'migration_running' => __( 'A database migration lock is active. Wait for its owner to finish, then recheck; do not delete an active lock.', 'overcustomise' ),
			'storage_blocked' => __( 'Private storage validation failed. Check writable directories and a private root outside web roots. CLI needs live HTTP-validated evidence or independently verified operator configuration. Uploads storage needs verified HTTPS denial or exact-root oc_private_storage_web_protected attestation. Never bypass protection.', 'overcustomise' ),
			'storage_root_overlap' => __( 'A private root overlaps the document root. Configure a separate private location and verify all deployment routes before rechecking.', 'overcustomise' ),
			'storage_evidence_missing' => __( 'Private-root HTTP evidence is missing or expired. Renew it through a real validated HTTP request, or configure an independently verified exact operator root for CLI.', 'overcustomise' ),
			'storage_http_verification_blocked' => __( 'HTTPS storage denial could not be established or automatic verification is disabled. Check the uploads HTTPS URL, loopback access and origin/CDN denial, or configure exact-root operator verification. Unknown aliases and mirrors require separate checks.', 'overcustomise' ),
			'storage_http_verification_deferred' => __( 'Storage HTTP verification was deferred or could not acquire its lock. Let the current check finish, check lock-directory permissions if needed, then recheck; do not bypass verification.', 'overcustomise' ),
			'relocation_storage_blocked' => __( 'Relocation is blocked by unavailable verified destination storage. Sources and existing metadata are retained. Restore protected writable storage before retrying the affected relocation.', 'overcustomise' ),
			'relocation_source_review' => __( 'A relocation source or record is missing, unreadable, changed, outside known roots or over its copy limit. Review the affected records and backups; preserve sources and metadata rather than forcing publication.', 'overcustomise' ),
			'relocation_publication_blocked' => __( 'Relocation could not read its batch, verify a copy or safely publish its pointer. Check database access, filesystem permissions and atomic hard-link support. Retain source and destination copies until references are reconciled.', 'overcustomise' ),
			'relocation_source_retained' => __( 'A private copy was published but its old source remains. Arrange reference-safe source cleanup and verify HTTP denial and CDN purge for old locations. A private destination does not secure the retained source.', 'overcustomise' ),
			'storage_diagnostic_unknown' => __( 'An unrecognized storage diagnostic was recorded. Review storage configuration and protected administrator logs, then recheck. Raw diagnostic details are intentionally omitted.', 'overcustomise' ),
			default => __( 'Recheck compatibility readiness.', 'overcustomise' ),
		};
	}

	/** Lightweight on ordinary admin visits: probes are cached for five minutes. */
	public static function readiness_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || wp_doing_ajax() ) {
			return;
		}
		$report = self::readiness_report();
		$states = array_merge( [ 'schema' => $report['schema'], 'migration' => $report['migration'] ], $report['tables'], $report['storage'], $report['storage_upgrade'] );
		$failed = array_filter( $states, static fn ( string $status ): bool => 'ready' !== $status );
		if ( empty( $failed ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'OverCustomise compatibility readiness needs attention', 'overcustomise' ) . '</strong></p><ul>';
		foreach ( $failed as $resource => $status ) {
			echo '<li><strong>' . esc_html( $resource . ': ' . $status ) . '</strong> ' . esc_html( self::readiness_guidance( $status ) ) . '</li>';
		}
		echo '</ul><p>' . esc_html__( 'Warnings do not disable the plugin; live guards apply to affected operations. This report refreshes within five minutes; rechecking does not migrate tables, move files or clear migration locks.', 'overcustomise' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="oc_recheck_readiness">';
		wp_nonce_field( 'oc_recheck_readiness' );
		echo '<p><button class="button" type="submit">' . esc_html__( 'Recheck Readiness', 'overcustomise' ) . '</button></p></form></div>';
	}

	/** Clear only the diagnostic cache after a capability- and nonce-protected POST. */
	public static function recheck_readiness(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), '', [ 'response' => 403 ] );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'This action requires a POST request.', 'overcustomise' ), '', [ 'response' => 405 ] );
		}
		check_admin_referer( 'oc_recheck_readiness' );
		delete_transient( self::READINESS_CACHE );
		self::readiness_report( true );
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-settings&tab=system' ) );
		exit;
	}

	/** Return all required and recommended runtime dependency checks. */
	public static function checks(): array {
		global $wp_version;

		$tcpdf_fonts = OC_PATH . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json';
		$ghostscript = self::ghostscript();

		$readiness = [];
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$report = self::readiness_report();
			$states = array_merge( [ 'schema' => $report['schema'], 'migration' => $report['migration'] ], $report['tables'], $report['storage'], $report['storage_upgrade'] );
			foreach ( $states as $resource => $status ) {
				$readiness[] = self::check( 'readiness_' . $resource, $resource, 'ready' === $status, $status, ! array_key_exists( $resource, $report['storage_upgrade'] ), self::readiness_guidance( $status ) );
			}
		}

		return array_merge( $readiness, [
			self::check( 'php', __( 'PHP 8.2 or newer', 'overcustomise' ), PHP_VERSION_ID >= 80200, PHP_VERSION, true, __( 'Required by OverCustomise and its Composer packages.', 'overcustomise' ) ),
			self::check( 'wordpress', __( 'WordPress 6.8 or newer', 'overcustomise' ), version_compare( (string) $wp_version, '6.8', '>=' ), (string) $wp_version, true, __( 'Required by the plugin metadata.', 'overcustomise' ) ),
			self::check( 'woocommerce', __( 'WooCommerce', 'overcustomise' ), class_exists( 'WooCommerce' ), defined( 'WC_VERSION' ) ? WC_VERSION : '', true, __( 'Required for products, carts, orders, and administration.', 'overcustomise' ) ),
			self::check( 'tcpdf', __( 'TCPDF and core fonts', 'overcustomise' ), class_exists( '\TCPDF' ) && is_readable( $tcpdf_fonts ), class_exists( '\TCPDF' ) ? __( 'Loaded', 'overcustomise' ) : '', true, __( 'Required to create production PDF/X files.', 'overcustomise' ) ),
			self::extension_check( 'bcmath', 'BCMath', true, __( 'Required by PDF generation dependencies.', 'overcustomise' ) ),
			self::extension_check( 'curl', 'cURL', true, __( 'Required by bundled PDF libraries and remote service requests.', 'overcustomise' ) ),
			self::extension_check( 'gd', 'GD', true, __( 'Required for raster artwork processing and previews.', 'overcustomise' ) ),
			self::extension_check( 'mbstring', 'Multibyte String', true, __( 'Required for Unicode text and font processing.', 'overcustomise' ) ),
			self::extension_check( 'openssl', 'OpenSSL', true, __( 'Required to encrypt stored API credentials.', 'overcustomise' ) ),
			self::extension_check( 'zlib', 'zlib', true, __( 'Required for compressed fonts and PDF data.', 'overcustomise' ) ),
			self::extension_check( 'dom', 'DOM', true, __( 'Required for safe SVG parsing and vector rendering.', 'overcustomise' ) ),
			self::extension_check( 'xmlreader', 'XMLReader', true, __( 'Required for safe SVG validation.', 'overcustomise' ) ),
			self::extension_check( 'fileinfo', 'Fileinfo', true, __( 'Required for reliable customer artwork type detection.', 'overcustomise' ) ),
			self::check( 'proc_open', 'proc_open', function_exists( 'proc_open' ), function_exists( 'proc_open' ) ? __( 'Enabled', 'overcustomise' ) : '', false, __( 'Recommended to run optional Ghostscript processing without invoking a shell.', 'overcustomise' ) ),
			self::check( 'ghostscript', 'Ghostscript', '' !== $ghostscript['binary'], $ghostscript['version'], false, __( 'Recommended to convert production PDF text into vector outlines; embedded-font PDFs are generated when unavailable.', 'overcustomise' ) ),
			self::extension_check( 'imagick', 'Imagick', false, __( 'Recommended for higher-quality artwork conversion and image effects; GD fallbacks remain available.', 'overcustomise' ) ),
			self::check(
				'imagick_heic',
				__( 'ImageMagick HEIC/HEIF support', 'overcustomise' ),
				class_exists( 'OC_Upload_Handler' ) && OC_Upload_Handler::heic_conversion_is_available(),
				class_exists( 'OC_Upload_Handler' ) && OC_Upload_Handler::heic_conversion_is_available() ? __( 'Available', 'overcustomise' ) : '',
				false,
				__( 'Required to convert Apple HEIC and HEIF photo uploads into filter-compatible JPEG images.', 'overcustomise' )
			),
		] );
	}

	/** Find an executable Ghostscript binary and report its version. */
	public static function ghostscript(): array {
		static $status = null;
		if ( is_array( $status ) ) {
			return $status;
		}

		$status = [ 'binary' => '', 'version' => '' ];
		if ( ! function_exists( 'proc_open' ) || ! class_exists( 'OC_Command_Runner' ) ) {
			return $status;
		}

		$candidates = str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' )
			? [ 'gswin64c', 'gswin32c', 'gs' ]
			: [ 'gs', '/usr/bin/gs', '/usr/local/bin/gs' ];
		$filtered   = apply_filters( 'oc_ghostscript_binary_candidates', $candidates );
		$candidates = is_array( $filtered ) ? $filtered : $candidates;

		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) {
				continue;
			}
			try {
				$probe = OC_Command_Runner::run( [ $candidate, '--version' ] );
				if ( 0 === (int) $probe['code'] ) {
					$version = trim( (string) ( $probe['output'][0] ?? '' ) );
					$status = [
						'binary'  => $candidate,
						'version' => '' !== $version ? $version . ' (' . $candidate . ')' : $candidate,
					];
					return $status;
				}
			} catch ( \InvalidArgumentException $e ) {
				// Continue through the allowlisted candidates.
			}
		}

		return $status;
	}

	/** Build one PHP extension status row. */
	private static function extension_check( string $extension, string $label, bool $required, string $description ): array {
		$loaded  = extension_loaded( $extension );
		$version = $loaded ? phpversion( $extension ) : false;

		return self::check(
			'ext_' . $extension,
			$label,
			$loaded,
			is_string( $version ) ? $version : ( $loaded ? __( 'Loaded', 'overcustomise' ) : '' ),
			$required,
			$description
		);
	}

	/** Normalize a dependency check for the admin renderer. */
	private static function check( string $key, string $label, bool $available, string $version, bool $required, string $description ): array {
		return compact( 'key', 'label', 'available', 'version', 'required', 'description' );
	}
}
