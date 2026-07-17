<?php
/**
 * Unit tests for bounded WOFF conversion and extraction.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class Test_WOFF_Converter extends TestCase {
	private array $temporary_files = [];

	protected function tearDown(): void {
		foreach ( $this->temporary_files as $path ) {
			if ( is_file( $path ) ) {
				@unlink( $path );
			}
		}
		parent::tearDown();
	}

	private function temporary_path( string $prefix ): string {
		$path = tempnam( sys_get_temp_dir(), $prefix );
		$this->temporary_files[] = $path;
		return $path;
	}

	#[Test]
	public function converts_and_extracts_a_bounded_sfnt_table(): void {
		$source    = $this->temporary_path( 'oc-sfnt-' );
		$woff      = $this->temporary_path( 'oc-woff-' );
		$extracted = $this->temporary_path( 'oc-font-' );
		$sfnt      = pack( 'Nnnnn', 0x00010000, 1, 16, 0, 0 )
			. 'test'
			. pack( 'NNN', 0, 28, 4 )
			. 'ABCD';
		file_put_contents( $source, $sfnt );

		$this->assertTrue( OC_WOFF_Converter::convert( $source, $woff ) );
		$this->assertTrue( OC_WOFF_Converter::extract_sfnt( $woff, $extracted ) );

		$output = file_get_contents( $extracted );
		$this->assertSame( "\x00\x01\x00\x00", substr( $output, 0, 4 ) );
		$this->assertSame( 'test', substr( $output, 12, 4 ) );
		$this->assertSame( 'ABCD', substr( $output, 28, 4 ) );
	}

	#[Test]
	public function rejects_a_woff_table_with_an_oversized_declared_output(): void {
		$source = $this->temporary_path( 'oc-bad-woff-' );
		$output = $this->temporary_path( 'oc-bad-font-' );
		$woff   = pack( 'NNNnnNnnNNNNN', 0x774F4646, 0x00010000, 64, 1, 0, 50331676, 1, 0, 0, 0, 0, 0, 0 )
			. 'test'
			. pack( 'NNNN', 64, 0, 50331648, 0 );
		file_put_contents( $source, $woff );

		$this->assertFalse( OC_WOFF_Converter::extract_sfnt( $source, $output ) );
	}

	#[Test]
	public function rejects_overlapping_sfnt_tables(): void {
		$source = $this->temporary_path( 'oc-overlap-sfnt-' );
		$output = $this->temporary_path( 'oc-overlap-woff-' );
		$sfnt   = pack( 'Nnnnn', 0x00010000, 2, 32, 1, 0 )
			. 'one1' . pack( 'NNN', 0, 44, 8 )
			. 'two2' . pack( 'NNN', 0, 48, 8 )
			. str_repeat( 'A', 12 );
		file_put_contents( $source, $sfnt );

		$this->assertFalse( OC_WOFF_Converter::convert( $source, $output ) );
	}
}
