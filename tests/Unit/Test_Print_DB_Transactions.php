<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-db.php';

class Test_Print_DB_Transactions extends TestCase {
	#[Test]
	public function unsafe_or_unknown_engines_block_every_transaction_before_mutation(): void {
		$previous = $GLOBALS['wpdb'] ?? null;
		try {
			foreach ( [ 'MyISAM', null, false, 'InnoDB' ] as $engine ) {
				$GLOBALS['wpdb'] = new class( $engine ) {
					public string $prefix = 'wp_';
					public string $last_error = '';
					public array $queries = [];
					public function __construct( public mixed $engine ) {}
					public function prepare( string $sql, mixed ...$args ): string { return $sql; }
					public function get_var( string $sql ): mixed {
						$this->last_error = 'InnoDB' === $this->engine ? 'Metadata query failed' : '';
						return $this->engine;
					}
					public function query( string $sql ): bool { $this->queries[] = $sql; return false; }
				};
				$this->assertFalse( OC_DB::update_print_files_atomically( [ 1 ], [ 'file_status' => 'expired' ] ) );
				$this->assertFalse( OC_DB::complete_queue_job( 1, 1, [ [ 'id' => 1 ] ] ) );
				$this->assertFalse( OC_DB::fail_queue_job( 1, 'processing', 3, 'error', [ 1 ] ) );
				$this->assertFalse( OC_DB::retry_failed_queue_job( 1, 3, [ 1 ] ) );
				$this->assertFalse( OC_DB::fail_unqueued_print_files( [ 1 ] ) );
				$this->assertSame( [], $GLOBALS['wpdb']->queries );
			}
		} finally {
			$GLOBALS['wpdb'] = $previous;
		}
	}

	#[Test]
	public function both_tables_must_support_transactions(): void {
		$previous = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb'] = new class {
			public string $prefix = 'wp_';
			public string $last_error = '';
			public array $engines = [ 'InnoDB', 'MyISAM' ];
			public function prepare( string $sql, mixed ...$args ): string { return $sql; }
			public function get_var( string $sql ): mixed { return array_shift( $this->engines ); }
		};
		try {
			$this->assertFalse( OC_DB::tables_support_transactions( [ 'oc_print_files', 'oc_print_queue' ] ) );
			$GLOBALS['wpdb']->engines = [ 'InnoDB', 'InnoDB' ];
			$this->assertTrue( OC_DB::tables_support_transactions( [ 'oc_print_files', 'oc_print_queue' ] ) );
		} finally {
			$GLOBALS['wpdb'] = $previous;
		}
	}
}
