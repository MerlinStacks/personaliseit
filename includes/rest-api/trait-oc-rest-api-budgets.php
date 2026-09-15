<?php
/**
 * Shared request budgets and option locks for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API; uses its authentication helpers and constants. */
trait OC_Rest_API_Budgets {

	/** Get a canonical client IP, trusting forwarding data only from explicit proxies. */
	private static function client_ip(): string {
		$remote = self::canonical_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' );
		if ( '' === $remote ) {
			return '';
		}

		$trusted = self::trusted_proxy_ranges();
		if ( ! self::ip_matches_any_range( $remote, $trusted ) ) {
			return $remote;
		}

		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
		if ( '' !== trim( $forwarded ) ) {
			$chain = [];
			foreach ( explode( ',', $forwarded ) as $candidate ) {
				$ip = self::canonical_ip( trim( $candidate ) );
				if ( '' === $ip ) {
					return $remote;
				}
				$chain[] = $ip;
			}
			if ( $chain ) {
				$chain[] = $remote;
				for ( $i = count( $chain ) - 1; $i >= 0; $i-- ) {
					if ( ! self::ip_matches_any_range( $chain[ $i ], $trusted ) ) {
						return $chain[ $i ];
					}
				}
				return $chain[0];
			}
		}

		$real_ip = self::canonical_ip( isset( $_SERVER['HTTP_X_REAL_IP'] ) ? (string) $_SERVER['HTTP_X_REAL_IP'] : '' );
		return '' !== $real_ip ? $real_ip : $remote;
	}

	/** Canonicalise an IPv4 or IPv6 address using PHP's IP validator. */
	private static function canonical_ip( string $ip ): string {
		$validated = filter_var( trim( $ip ), FILTER_VALIDATE_IP );
		if ( false === $validated ) {
			return '';
		}

		$packed = @inet_pton( $validated ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $packed ? '' : (string) inet_ntop( $packed );
	}

	/** Return validated explicit trusted proxy IP/CIDR entries. */
	private static function trusted_proxy_ranges(): array {
		$configured = defined( 'OC_TRUSTED_PROXIES' ) ? OC_TRUSTED_PROXIES : [];
		$configured = apply_filters( 'oc_trusted_proxy_ips', $configured );
		if ( is_string( $configured ) ) {
			$configured = preg_split( '/[\s,]+/', $configured, -1, PREG_SPLIT_NO_EMPTY );
		}
		if ( ! is_array( $configured ) ) {
			return [];
		}

		$ranges = [];
		foreach ( $configured as $range ) {
			if ( ! is_string( $range ) || '' === trim( $range ) ) {
				return [];
			}
			$parts  = explode( '/', trim( $range ), 2 );
			$ip     = self::canonical_ip( $parts[0] );
			$packed = '' !== $ip ? @inet_pton( $ip ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$bits   = is_string( $packed ) ? 8 * strlen( $packed ) : 0;
			$prefix = 1 === count( $parts ) ? $bits : ( preg_match( '/^[0-9]+$/D', $parts[1] ) ? (int) $parts[1] : -1 );
			if ( false === $packed || $prefix < 0 || $prefix > $bits ) {
				return [];
			}
			$ranges[] = [
				'network' => $packed,
				'prefix'  => $prefix,
			];
		}

		return $ranges;
	}

	/** Match an IP against validated IPv4/IPv6 CIDR entries. */
	private static function ip_matches_any_range( string $ip, array $ranges ): bool {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $packed ) {
			return false;
		}

		foreach ( $ranges as $range ) {
			$network = $range['network'] ?? '';
			$prefix  = (int) ( $range['prefix'] ?? -1 );
			if ( ! is_string( $network ) || strlen( $network ) !== strlen( $packed ) || $prefix < 0 ) {
				continue;
			}
			$bytes = intdiv( $prefix, 8 );
			$bits  = $prefix % 8;
			if ( $bytes > 0 && substr( $packed, 0, $bytes ) !== substr( $network, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $bits ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $bits ) ) & 0xff;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $network[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	/** Read a bounded integer filter without coercing malformed values. */
	private static function filtered_limit( string $filter, int $default, int $minimum, int $maximum ): ?int {
		$value = apply_filters( $filter, $default );
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/D', $value ) ) ) {
			return null;
		}
		$value = (int) $value;
		return $value >= $minimum && $value <= $maximum ? $value : null;
	}

	/** Return the current fixed UTC hourly window. */
	private static function hourly_window(): array {
		$start = intdiv( time(), HOUR_IN_SECONDS ) * HOUR_IN_SECONDS;
		return [ $start, $start + HOUR_IN_SECONDS ];
	}

	/** Confirm wp_options can provide transactional row locks for budget updates. */
	private static function options_support_transactions(): bool {
		return OC_DB::tables_support_transactions( [ 'options' ] );
	}

	/** Map a logical security budget to a non-sensitive option key. */
	private static function budget_option_name( string $key ): string {
		return 'oc_budget_' . hash( 'sha256', $key );
	}

	/** Validate, migrate, and prune one true rolling-window budget state. */
	private static function normalise_sliding_budget_state( array $state, int $window_seconds, int $now ): array {
		if ( $window_seconds < 1 || $window_seconds > DAY_IN_SECONDS ) {
			throw new \RuntimeException( 'A sliding security budget has an invalid duration.' );
		}

		// Version 1 stored one aggregate that expired after the last request. Preserve
		// its existing expiry as one conservative bucket during lazy migration.
		if ( 1 === ( $state['version'] ?? null ) && ! empty( $state['sliding_window'] ) ) {
			if ( ! is_int( $state['window_end'] ?? null ) || ! is_int( $state['count'] ?? null ) || ! is_int( $state['bytes'] ?? null )
				|| $state['count'] < 0 || $state['bytes'] < 0
			) {
				throw new \RuntimeException( 'A legacy sliding security budget row is malformed.' );
			}
			$buckets = [];
			if ( $state['window_end'] > $now && ( $state['count'] > 0 || $state['bytes'] > 0 ) ) {
				$buckets[] = [
					'timestamp' => $state['window_end'] - $window_seconds,
					'count'     => $state['count'],
					'bytes'     => $state['bytes'],
				];
			}
			$state = [
				'version'        => 2,
				'window_type'    => 'sliding',
				'window_seconds' => $window_seconds,
				'buckets'        => $buckets,
			];
		}

		if ( 2 !== ( $state['version'] ?? null ) || 'sliding' !== ( $state['window_type'] ?? null )
			|| ! is_int( $state['window_seconds'] ?? null ) || $state['window_seconds'] !== $window_seconds
			|| ! is_array( $state['buckets'] ?? null ) || ! array_is_list( $state['buckets'] )
			|| count( $state['buckets'] ) > $window_seconds + 1
		) {
			throw new \RuntimeException( 'A persisted sliding security budget row is malformed.' );
		}

		$cutoff  = $now - $window_seconds;
		$buckets = [];
		$last    = 0;
		foreach ( $state['buckets'] as $bucket ) {
			if ( ! is_array( $bucket ) || ! is_int( $bucket['timestamp'] ?? null ) || ! is_int( $bucket['count'] ?? null )
				|| ! is_int( $bucket['bytes'] ?? null ) || $bucket['timestamp'] <= $last || $bucket['timestamp'] > $now
				|| $bucket['count'] < 0 || $bucket['bytes'] < 0 || ( 0 === $bucket['count'] && 0 === $bucket['bytes'] )
			) {
				throw new \RuntimeException( 'A persisted sliding security budget bucket is malformed.' );
			}
			$last = $bucket['timestamp'];
			if ( $bucket['timestamp'] > $cutoff ) {
				$buckets[] = $bucket;
			}
		}
		$state['buckets'] = $buckets;
		return $state;
	}

	/** Return rolling-window usage totals. */
	private static function sliding_budget_totals( array $buckets ): array {
		$count = 0;
		$bytes = 0;
		foreach ( $buckets as $bucket ) {
			$count += (int) $bucket['count'];
			$bytes += (int) $bucket['bytes'];
		}
		return [ $count, $bytes ];
	}

	/** Calculate when enough oldest rolling usage expires for this reservation. */
	private static function sliding_budget_retry_after( array $buckets, array $spec, int $now, int $window_seconds ): int {
		[ $count, $bytes ] = self::sliding_budget_totals( $buckets );
		foreach ( $buckets as $bucket ) {
			$count -= (int) $bucket['count'];
			$bytes -= (int) $bucket['bytes'];
			if ( $count <= $spec['count_limit'] - $spec['count'] && $bytes <= $spec['byte_limit'] - $spec['bytes'] ) {
				return max( 1, (int) $bucket['timestamp'] + $window_seconds - $now );
			}
		}
		return $window_seconds;
	}

	/**
	 * Atomically reserve count/byte capacity across every supplied budget.
	 *
	 * @return array{items:array<string,array>}|\WP_Error
	 */
	private static function reserve_budgets( array $specs ): array|\WP_Error {
		if ( empty( $specs ) ) {
			return [ 'items' => [] ];
		}
		if ( ! self::options_support_transactions() ) {
			OC_Logger::error( 'Security budgets require a transactional wp_options table.' );
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$prepared = [];
		$now      = time();
		foreach ( $specs as $spec ) {
			$key          = is_string( $spec['key'] ?? null ) ? $spec['key'] : '';
			$window_start = (int) ( $spec['window_start'] ?? 0 );
			$window_end   = (int) ( $spec['window_end'] ?? 0 );
			$count        = (int) ( $spec['count'] ?? 0 );
			$bytes        = (int) ( $spec['bytes'] ?? 0 );
			$count_limit  = (int) ( $spec['count_limit'] ?? 0 );
			$byte_limit   = (int) ( $spec['byte_limit'] ?? 0 );
			$option_name  = self::budget_option_name( $key );
			if ( '' === $key || $window_start <= 0 || $window_end <= $now || $window_end <= $window_start
				|| $count < 0 || $bytes < 0 || ( 0 === $count && 0 === $bytes )
				|| ( $count > 0 && $count_limit < $count ) || ( $bytes > 0 && $byte_limit < $bytes )
				|| isset( $prepared[ $option_name ] )
			) {
				OC_Logger::error( 'A malformed security budget reservation was rejected.' );
				return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$prepared[ $option_name ] = [
				'option_name'    => $option_name,
				'window_start'   => $window_start,
				'window_end'     => $window_end,
				'count'          => $count,
				'bytes'          => $bytes,
				'count_limit'    => $count_limit,
				'byte_limit'     => $byte_limit,
				'count_mode'     => (string) ( $spec['count_mode'] ?? 'none' ),
				'bytes_mode'     => (string) ( $spec['bytes_mode'] ?? 'none' ),
				'sliding_window' => ! empty( $spec['sliding_window'] ),
				'error_code'     => sanitize_key( (string) ( $spec['error_code'] ?? 'rate_limited' ) ),
				'error_message'  => (string) ( $spec['error_message'] ?? __( 'The request limit has been reached. Please try again later.', 'overcustomise' ) ),
				'error_status'   => (int) ( $spec['error_status'] ?? 429 ),
			];
		}
		ksort( $prepared, SORT_STRING );

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			OC_Logger::error( 'Security budget transaction could not start: ' . $wpdb->last_error );
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$inserted = [];
		$error    = null;
		try {
			foreach ( $prepared as $option_name => $spec ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
						$option_name,
						'{}'
					)
				);
				if ( false === $result ) {
					throw new \RuntimeException( 'A security budget row could not be created: ' . $wpdb->last_error );
				}
				$inserted[ $option_name ] = 1 === $result;
			}

			foreach ( $prepared as $option_name => $spec ) {
				$raw   = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
						$option_name
					)
				);
				$state = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( $spec['sliding_window'] ) {
					$window_seconds = $spec['window_end'] - $spec['window_start'];
					if ( $inserted[ $option_name ] ) {
						$state = [
							'version'        => 2,
							'window_type'    => 'sliding',
							'window_seconds' => $window_seconds,
							'buckets'        => [],
						];
					}
					if ( ! is_array( $state ) ) {
						throw new \RuntimeException( 'A persisted sliding security budget row is malformed.' );
					}
					$state                             = self::normalise_sliding_budget_state( $state, $window_seconds, $now );
					[ $current_count, $current_bytes ] = self::sliding_budget_totals( $state['buckets'] );
					if ( ( $spec['count'] > 0 && $current_count > $spec['count_limit'] - $spec['count'] )
						|| ( $spec['bytes'] > 0 && $current_bytes > $spec['byte_limit'] - $spec['bytes'] )
					) {
						$retry_after = self::sliding_budget_retry_after( $state['buckets'], $spec, $now, $window_seconds );
						$message     = match ( $spec['error_code'] ) {
							'ai_quota_exceeded'            => self::ai_quota_retry_message( $retry_after ),
							'ai_generation_quota_exceeded' => self::ai_generation_quota_retry_message( $retry_after ),
							default                        => $spec['error_message'],
						};
						$error = new \WP_Error(
							$spec['error_code'],
							$message,
							[
								'status'      => $spec['error_status'],
								'retry_after' => $retry_after,
							]
						);
						throw new \OverflowException( 'Security budget exhausted.' );
					}
					$last_index = count( $state['buckets'] ) - 1;
					if ( $last_index >= 0 && $state['buckets'][ $last_index ]['timestamp'] === $now ) {
						$state['buckets'][ $last_index ]['count'] += $spec['count'];
						$state['buckets'][ $last_index ]['bytes'] += $spec['bytes'];
					} else {
						$state['buckets'][] = [
							'timestamp' => $now,
							'count'     => $spec['count'],
							'bytes'     => $spec['bytes'],
						];
					}
					$prepared[ $option_name ]['bucket_timestamp'] = $now;
				} elseif ( $inserted[ $option_name ] ) {
					$state = [
						'version'        => 1,
						'window_start'   => $spec['window_start'],
						'window_end'     => $spec['window_end'],
						'count'          => 0,
						'bytes'          => 0,
						'sliding_window' => false,
					];
				} elseif ( ! is_array( $state )
					|| ! is_int( $state['version'] ?? null ) || 1 !== $state['version']
					|| ! is_int( $state['window_start'] ?? null ) || $state['window_start'] <= 0
					|| ! is_int( $state['window_end'] ?? null ) || $state['window_end'] <= $state['window_start']
					|| ! is_int( $state['count'] ?? null ) || $state['count'] < 0
					|| ! is_int( $state['bytes'] ?? null ) || $state['bytes'] < 0
				) {
					throw new \RuntimeException( 'A persisted security budget row is malformed.' );
				} elseif ( ! empty( $state['sliding_window'] ) || (int) $state['window_end'] <= $now
				) {
					$state = [
						'version'        => 1,
						'window_start'   => $spec['window_start'],
						'window_end'     => $spec['window_end'],
						'count'          => 0,
						'bytes'          => 0,
						'sliding_window' => false,
					];
				} elseif ( (int) $state['window_start'] !== $spec['window_start'] || (int) $state['window_end'] !== $spec['window_end']
				) {
					throw new \RuntimeException( 'An active security budget has an unexpected time window.' );
				}

				if ( ! $spec['sliding_window'] ) {
					$current_count = (int) $state['count'];
					$current_bytes = (int) $state['bytes'];
					if ( ( $spec['count'] > 0 && $current_count > $spec['count_limit'] - $spec['count'] )
					|| ( $spec['bytes'] > 0 && $current_bytes > $spec['byte_limit'] - $spec['bytes'] )
					) {
						$retry_after = max( 1, $spec['window_end'] - $now );
						$message     = $spec['error_message'];
						$message     = match ( $spec['error_code'] ) {
							'ai_quota_exceeded'            => self::ai_quota_retry_message( $retry_after ),
							'ai_generation_quota_exceeded' => self::ai_generation_quota_retry_message( $retry_after ),
							default                        => $message,
						};
						$error = new \WP_Error(
							$spec['error_code'],
							$message,
							[
								'status'      => $spec['error_status'],
								'retry_after' => $retry_after,
							]
						);
						throw new \OverflowException( 'Security budget exhausted.' );
					}
					$state['count'] = $current_count + $spec['count'];
					$state['bytes'] = $current_bytes + $spec['bytes'];
				}
				$encoded = wp_json_encode( $state );
				if ( ! is_string( $encoded ) ) {
					throw new \RuntimeException( 'A security budget row could not be encoded.' );
				}
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
						$encoded,
						$option_name
					)
				);
				if ( 1 !== $updated ) {
					throw new \RuntimeException( 'A security budget row could not be reserved: ' . $wpdb->last_error );
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'A security budget transaction could not commit: ' . $wpdb->last_error );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			if ( null === $error ) {
				OC_Logger::error( 'Security budget reservation failed: ' . $e->getMessage() );
				$error = new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
			}
		}

		foreach ( array_keys( $prepared ) as $option_name ) {
			wp_cache_delete( $option_name, 'options' );
		}
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return [ 'items' => $prepared ];
	}

	/** Atomically release all or part of a prior budget reservation. */
	private static function reduce_budget_reservation( array $reservation, array $reductions ): bool {
		$items = is_array( $reservation['items'] ?? null ) ? $reservation['items'] : [];
		$work  = [];
		foreach ( $items as $option_name => $item ) {
			$reduce = $reductions[ $option_name ] ?? [];
			$count  = max( 0, (int) ( $reduce['count'] ?? 0 ) );
			$bytes  = max( 0, (int) ( $reduce['bytes'] ?? 0 ) );
			if ( $count > 0 || $bytes > 0 ) {
				$work[ $option_name ] = [
					'window_start'     => (int) ( $item['window_start'] ?? 0 ),
					'bucket_timestamp' => (int) ( $item['bucket_timestamp'] ?? 0 ),
					'sliding_window'   => ! empty( $item['sliding_window'] ),
					'count'            => min( $count, (int) ( $item['count'] ?? 0 ) ),
					'bytes'            => min( $bytes, (int) ( $item['bytes'] ?? 0 ) ),
				];
			}
		}
		if ( empty( $work ) ) {
			return true;
		}
		ksort( $work, SORT_STRING );

		global $wpdb;
		if ( ! self::options_support_transactions() || false === $wpdb->query( 'START TRANSACTION' ) ) {
			OC_Logger::error( 'Security budget release transaction could not start.' );
			return false;
		}

		try {
			foreach ( $work as $option_name => $reduce ) {
				$raw   = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
						$option_name
					)
				);
				$state = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( ! is_array( $state ) ) {
					throw new \RuntimeException( 'A reserved security budget row is unavailable.' );
				}
				if ( $reduce['sliding_window'] ) {
					if ( 2 !== ( $state['version'] ?? null ) || 'sliding' !== ( $state['window_type'] ?? null ) || ! is_array( $state['buckets'] ?? null ) ) {
						throw new \RuntimeException( 'A reserved sliding security budget row is unavailable.' );
					}
					foreach ( $state['buckets'] as $index => $bucket ) {
						if ( (int) ( $bucket['timestamp'] ?? 0 ) !== $reduce['bucket_timestamp'] ) {
							continue;
						}
						$state['buckets'][ $index ]['count'] = max( 0, (int) ( $bucket['count'] ?? 0 ) - $reduce['count'] );
						$state['buckets'][ $index ]['bytes'] = max( 0, (int) ( $bucket['bytes'] ?? 0 ) - $reduce['bytes'] );
						if ( 0 === $state['buckets'][ $index ]['count'] && 0 === $state['buckets'][ $index ]['bytes'] ) {
							array_splice( $state['buckets'], $index, 1 );
						}
						break;
					}
				} else {
					if ( 1 !== ( $state['version'] ?? null ) || ! is_int( $state['window_start'] ?? null )
						|| ! is_int( $state['count'] ?? null ) || ! is_int( $state['bytes'] ?? null ) || $state['count'] < 0 || $state['bytes'] < 0
					) {
						throw new \RuntimeException( 'A reserved security budget row is unavailable.' );
					}
					if ( (int) ( $state['window_start'] ?? 0 ) !== $reduce['window_start'] ) {
						continue;
					}
					$state['count'] = max( 0, (int) ( $state['count'] ?? 0 ) - $reduce['count'] );
					$state['bytes'] = max( 0, (int) ( $state['bytes'] ?? 0 ) - $reduce['bytes'] );
				}
				$encoded = wp_json_encode( $state );
				$updated = is_string( $encoded ) ? $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
						$encoded,
						$option_name
					)
				) : false;
				if ( 1 !== $updated ) {
					throw new \RuntimeException( 'A security budget row could not be released: ' . $wpdb->last_error );
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'A security budget release could not commit: ' . $wpdb->last_error );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			OC_Logger::error( 'Security budget release failed: ' . $e->getMessage() );
			return false;
		} finally {
			foreach ( array_keys( $work ) as $option_name ) {
				wp_cache_delete( $option_name, 'options' );
			}
		}

		return true;
	}

	/** Release an entire failed request reservation. */
	private static function release_budget_reservation( array $reservation ): bool {
		$reductions = [];
		foreach ( (array) ( $reservation['items'] ?? [] ) as $option_name => $item ) {
			$reductions[ $option_name ] = [
				'count' => (int) ( $item['count'] ?? 0 ),
				'bytes' => (int) ( $item['bytes'] ?? 0 ),
			];
		}
		return self::reduce_budget_reservation( $reservation, $reductions );
	}

	/** Ensure actual persisted usage cannot exceed a conservative reservation. */
	private static function reservation_covers_usage( array $reservation, int $attachment_count, int $bytes ): bool {
		foreach ( (array) ( $reservation['items'] ?? [] ) as $item ) {
			$needed_count = match ( $item['count_mode'] ?? 'none' ) {
				'request'     => 1,
				'attachments' => $attachment_count,
				default       => 0,
			};
			$needed_bytes = 'actual' === ( $item['bytes_mode'] ?? 'none' ) ? $bytes : 0;
			if ( $needed_count > (int) ( $item['count'] ?? 0 ) || $needed_bytes > (int) ( $item['bytes'] ?? 0 ) ) {
				return false;
			}
		}
		return true;
	}

	/** Retain actual usage and release the conservative remainder. */
	private static function finalise_budget_reservation( array $reservation, int $attachment_count, int $bytes ): bool {
		if ( ! self::reservation_covers_usage( $reservation, $attachment_count, $bytes ) ) {
			return false;
		}

		$reductions = [];
		foreach ( (array) ( $reservation['items'] ?? [] ) as $option_name => $item ) {
			$keep_count = match ( $item['count_mode'] ?? 'none' ) {
				'request'     => 1,
				'attachments' => $attachment_count,
				default       => 0,
			};
			$keep_bytes                 = 'actual' === ( $item['bytes_mode'] ?? 'none' ) ? $bytes : 0;
			$reductions[ $option_name ] = [
				'count' => max( 0, (int) ( $item['count'] ?? 0 ) - $keep_count ),
				'bytes' => max( 0, (int) ( $item['bytes'] ?? 0 ) - $keep_bytes ),
			];
		}
		return self::reduce_budget_reservation( $reservation, $reductions );
	}

	/** Build atomic per-IP/site/token storage budgets for uploads or AI results. */
	private static function upload_capacity_specs( int $bytes, int $attachment_count, string $token, ?array $token_state, bool $count_request ): array|\WP_Error {
		$ip = self::client_ip();
		if ( '' === $ip ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$ip_count_limit    = self::filtered_limit( 'oc_artwork_upload_ip_hourly_limit', 60, 1, 10000 );
		$ip_byte_limit     = self::filtered_limit( 'oc_artwork_upload_ip_hourly_bytes', 1073741824, 1048576, 107374182400 );
		$site_byte_limit   = self::filtered_limit( 'oc_artwork_upload_site_hourly_bytes', 10737418240, 1048576, 1099511627776 );
		$token_count_limit = self::filtered_limit( 'oc_public_token_attachment_limit', 50, 1, 1000 );
		$token_byte_limit  = self::filtered_limit( 'oc_public_token_attachment_bytes', 536870912, 1048576, 107374182400 );
		if ( null === $ip_count_limit || null === $ip_byte_limit || null === $site_byte_limit
			|| null === $token_count_limit || null === $token_byte_limit
		) {
			OC_Logger::error( 'Artwork security budget filters returned malformed limits.' );
			return new \WP_Error( 'security_budget_unavailable', __( 'Artwork uploads are temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		[ $window_start, $window_end ] = self::hourly_window();
		$message                       = __( 'The artwork upload limit has been reached. Please try again later.', 'overcustomise' );
		$specs                         = [
			[
				'key'           => 'upload:ip:' . hash( 'sha256', $ip ),
				'window_start'  => $window_start,
				'window_end'    => $window_end,
				'count'         => $count_request ? 1 : 0,
				'bytes'         => $bytes,
				'count_limit'   => $ip_count_limit,
				'byte_limit'    => $ip_byte_limit,
				'count_mode'    => $count_request ? 'request' : 'none',
				'bytes_mode'    => 'actual',
				'error_code'    => 'upload_limit_reached',
				'error_message' => $message,
			],
			[
				'key'           => 'upload:site:' . (int) get_current_blog_id(),
				'window_start'  => $window_start,
				'window_end'    => $window_end,
				'count'         => 0,
				'bytes'         => $bytes,
				'count_limit'   => 0,
				'byte_limit'    => $site_byte_limit,
				'count_mode'    => 'none',
				'bytes_mode'    => 'actual',
				'error_code'    => 'upload_limit_reached',
				'error_message' => $message,
			],
		];

		if ( '' !== $token ) {
			if ( ! is_array( $token_state ) ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$specs[] = [
				'key'           => 'upload:token:' . hash( 'sha256', $token ),
				'window_start'  => (int) $token_state['created_at'],
				'window_end'    => (int) $token_state['expires_at'],
				'count'         => $attachment_count,
				'bytes'         => $bytes,
				'count_limit'   => $token_count_limit,
				'byte_limit'    => $token_byte_limit,
				'count_mode'    => 'attachments',
				'bytes_mode'    => 'actual',
				'error_code'    => 'token_upload_limit_reached',
				'error_message' => __( 'This upload session has reached its artwork limit. Please refresh and try again.', 'overcustomise' ),
			];
		}

		return $specs;
	}

	/** Build atomic preview request/byte budgets. */
	private static function preview_budget_specs( int $bytes ): array|\WP_Error {
		$ip              = self::client_ip();
		$ip_count_limit  = self::filtered_limit( 'oc_preview_ip_hourly_limit', 30, 1, 10000 );
		$ip_byte_limit   = self::filtered_limit( 'oc_preview_ip_hourly_bytes', 314572800, 1048576, 107374182400 );
		$site_byte_limit = self::filtered_limit( 'oc_preview_site_hourly_bytes', 5368709120, 1048576, 1099511627776 );
		if ( '' === $ip || null === $ip_count_limit || null === $ip_byte_limit || null === $site_byte_limit ) {
			OC_Logger::error( 'Preview security budgets are unavailable or malformed.' );
			return new \WP_Error( 'security_budget_unavailable', __( 'Preview storage is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		[ $window_start, $window_end ] = self::hourly_window();
		$message                       = __( 'The preview upload limit has been reached. Please try again later.', 'overcustomise' );
		return [
			[
				'key'           => 'preview:ip:' . hash( 'sha256', $ip ),
				'window_start'  => $window_start,
				'window_end'    => $window_end,
				'count'         => 1,
				'bytes'         => $bytes,
				'count_limit'   => $ip_count_limit,
				'byte_limit'    => $ip_byte_limit,
				'count_mode'    => 'request',
				'bytes_mode'    => 'actual',
				'error_code'    => 'preview_limit_reached',
				'error_message' => $message,
			],
			[
				'key'           => 'preview:site:' . (int) get_current_blog_id(),
				'window_start'  => $window_start,
				'window_end'    => $window_end,
				'count'         => 0,
				'bytes'         => $bytes,
				'count_limit'   => 0,
				'byte_limit'    => $site_byte_limit,
				'count_mode'    => 'none',
				'bytes_mode'    => 'actual',
				'error_code'    => 'preview_limit_reached',
				'error_message' => $message,
			],
		];
	}

	/** Build all-or-none paid AI actor/IP/site quota rows. */
	private static function ai_quota_specs( string $actor ): array|\WP_Error {
		return self::paid_ai_quota_specs( $actor, false );
	}

	/** Build isolated text-to-image actor/IP/site quota rows. */
	private static function ai_generation_quota_specs( string $actor ): array|\WP_Error {
		return self::paid_ai_quota_specs( $actor, true );
	}

	/** Build paid AI quotas without sharing hooks or storage keys between features. */
	private static function paid_ai_quota_specs( string $actor, bool $text_to_image ): array|\WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return [];
		}

		$ip            = self::client_ip();
		$filter_prefix = $text_to_image ? 'oc_ai_generation_' : 'oc_ai_filter_';
		$key_prefix    = $text_to_image ? 'ai-generation:' : 'ai:';
		$actor_limit   = self::filtered_limit( $filter_prefix . 'actor_hourly_limit', 5, 1, 100 );
		$ip_limit      = self::filtered_limit( $filter_prefix . 'ip_hourly_limit', 10, 1, 500 );
		$site_limit    = self::filtered_limit( $filter_prefix . 'site_hourly_limit', 100, 1, 10000 );
		if ( '' === $actor || '' === $ip || null === $actor_limit || null === $ip_limit || null === $site_limit ) {
			OC_Logger::error( 'AI quota configuration is unavailable or malformed.' );
			$message = $text_to_image ? __( 'Image generation is temporarily unavailable.', 'overcustomise' ) : __( 'Image processing is temporarily unavailable.', 'overcustomise' );
			return new \WP_Error( 'ai_quota_unavailable', $message, [ 'status' => 503 ] );
		}

		$window_start = time();
		$window_end   = $window_start + 15 * MINUTE_IN_SECONDS;
		$message      = $text_to_image ? __( 'The image generation limit has been reached. Please try again later.', 'overcustomise' ) : __( 'The image processing limit has been reached. Please try again later.', 'overcustomise' );
		$base         = [
			'window_start'   => $window_start,
			'window_end'     => $window_end,
			'count'          => 1,
			'bytes'          => 0,
			'byte_limit'     => 0,
			'sliding_window' => true,
			'count_mode'     => 'request',
			'bytes_mode'     => 'none',
			'error_code'     => $text_to_image ? 'ai_generation_quota_exceeded' : 'ai_quota_exceeded',
			'error_message'  => $message,
		];
		return [
			array_merge(
				$base,
				[
					'key'         => $key_prefix . 'actor:' . hash( 'sha256', $actor ),
					'count_limit' => $actor_limit,
				]
			),
			array_merge(
				$base,
				[
					'key'         => $key_prefix . 'ip:' . hash( 'sha256', $ip ),
					'count_limit' => $ip_limit,
				]
			),
			array_merge(
				$base,
				[
					'key'         => $key_prefix . 'site:' . (int) get_current_blog_id(),
					'count_limit' => $site_limit,
				]
			),
		];
	}

	/** Format an AI preview quota reset as customer-friendly rounded-up minutes. */
	private static function ai_quota_retry_message( int $retry_after ): string {
		$minutes = max( 1, (int) ceil( max( 1, $retry_after ) / MINUTE_IN_SECONDS ) );
		return sprintf(
			/* translators: %d: Number of minutes until another generated image is allowed. */
			_n(
				'You have reached the live photo preview limit. You can make more previews in %d minute.',
				'You have reached the live photo preview limit. You can make more previews in %d minutes.',
				$minutes,
				'overcustomise'
			),
			$minutes
		);
	}

	/** Format a text-to-image quota reset as customer-friendly rounded-up minutes. */
	private static function ai_generation_quota_retry_message( int $retry_after ): string {
		$minutes = max( 1, (int) ceil( max( 1, $retry_after ) / MINUTE_IN_SECONDS ) );
		return sprintf(
			/* translators: %d: Number of minutes until another generated image is allowed. */
			_n(
				'You have reached the image generation limit. You can generate another image in %d minute.',
				'You have reached the image generation limit. You can generate another image in %d minutes.',
				$minutes,
				'overcustomise'
			),
			$minutes
		);
	}

	/** Reserve one atomic per-IP request count after cheap validation has passed. */
	private static function reserve_request_rate( string $scope, int $limit, string $message ): array|\WP_Error {
		$ip = self::client_ip();
		if ( '' === $ip || $limit <= 0 ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		[ $window_start, $window_end ] = self::hourly_window();
		return self::reserve_budgets(
			[
				[
					'key'           => 'request:' . $scope . ':' . hash( 'sha256', $ip ),
					'window_start'  => $window_start,
					'window_end'    => $window_end,
					'count'         => 1,
					'bytes'         => 0,
					'count_limit'   => $limit,
					'byte_limit'    => 0,
					'count_mode'    => 'request',
					'bytes_mode'    => 'none',
					'error_code'    => 'rate_limited',
					'error_message' => $message,
				],
			]
		);
	}

	/** Acquire an expiring DB option lock without deleting another request's lock. */
	private static function acquire_option_lock( string $option_name, int $ttl ): string|\WP_Error {
		$ttl = max( 1, min( 3600, $ttl ) );
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$owner = ( time() + $ttl ) . '|' . wp_generate_uuid4();
			if ( add_option( $option_name, $owner, '', false ) ) {
				return $owner;
			}

			$current = (string) get_option( $option_name, '' );
			$parts   = explode( '|', $current, 2 );
			if ( 2 !== count( $parts ) || ! preg_match( '/^[0-9]+$/D', $parts[0] ) || (int) $parts[0] > time() ) {
				return new \WP_Error( 'locked', __( 'This request is already being processed.', 'overcustomise' ), [ 'status' => 409 ] );
			}
			if ( ! self::delete_owned_option( $option_name, $current ) ) {
				return new \WP_Error( 'locked', __( 'This request is already being processed.', 'overcustomise' ), [ 'status' => 409 ] );
			}
		}

		return new \WP_Error( 'locked', __( 'This request is already being processed.', 'overcustomise' ), [ 'status' => 409 ] );
	}

	/** Delete a lock only when it still belongs to the expected request. */
	private static function delete_owned_option( string $option_name, string $expected_value ): bool {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$option_name,
				$expected_value
			)
		);
		if ( 1 !== $deleted ) {
			return false;
		}

		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return true;
	}
}
