<?php
/**
 * GD rasterisation of the embroidery composer's own drawing commands.
 *
 * This is deliberately not a PostScript interpreter. Only the geometry emitted
 * by OC_Print_Embroidery is accepted; external EPS and executable operators fail.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

// Local generated streams need seekable binary I/O, not remote WP_Filesystem transports.
// phpcs:disable WordPress.WP.AlternativeFunctions

class OC_Embroidery_Raster {
	private const OPERANDS = [
		'translate'    => 2,
		'scale'        => 2,
		'rotate'       => 1,
		'concat'       => 6,
		'moveto'       => 2,
		'lineto'       => 2,
		'rlineto'      => 2,
		'curveto'      => 6,
		'arc'          => 5,
		'rectfill'     => 4,
		'setrgbcolor'  => 3,
		'setlinewidth' => 1,
	];

	private \GdImage $image;
	private int $width;
	private int $height;
	private int $work = 0;
	private array $matrix;
	private array $path       = [];
	private int $path_points  = 0;
	private array $stack      = [];
	private ?array $clip      = null;
	private int $colour       = 0;
	private float $line_width = 1.0;

	/** Render a local, internally composed file as an uncompressed 24-bit BMP. */
	public static function render( string $source, string $destination ): void {
		if ( ! function_exists( 'imagebmp' ) ) {
			throw new \RuntimeException( esc_html__( 'Embroidery BMP generation requires PHP GD with BMP support when Ghostscript is unavailable.', 'overcustomise' ) );
		}
		$stream = fopen( $source, 'rb' );
		if ( false === $stream ) {
			throw new \RuntimeException( 'Could not read composed embroidery artwork.' );
		}
		try {
			$header = fread( $stream, 4096 );
			if ( ! preg_match( '/%%BoundingBox: 0 0 (\d+) (\d+)/', (string) $header, $bounds ) ) {
				throw new \RuntimeException( 'Missing embroidery artwork dimensions.' );
			}
			$renderer = new self( (int) $bounds[1] * 2, (int) $bounds[2] * 2 );
			rewind( $stream );
			try {
				while ( false !== ( $line = fgets( $stream, 1048577 ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Stream one bounded line at a time.
					$renderer->draw( $line );
				}
				if ( ! feof( $stream ) || [] !== $renderer->stack ) {
					throw new \RuntimeException( 'Incomplete embroidery drawing.' );
				}
				if ( ! imagebmp( $renderer->image, $destination, false ) ) {
					throw new \RuntimeException( 'Could not write embroidery BMP artwork.' );
				}
				clearstatcache( true, $destination );
				$expected_size = 54 + ( ( $renderer->width * 3 + 3 ) & ~3 ) * $renderer->height;
				if ( filesize( $destination ) !== $expected_size ) {
					throw new \RuntimeException( 'Incomplete embroidery BMP artwork.' );
				}
				// GD's BMP encoder writes zero resolution, even after imageresolution().
				// BITMAPINFOHEADER stores horizontal/vertical pixels per metre here.
				$output = fopen( $destination, 'r+b' );
				if ( false === $output ) {
					throw new \RuntimeException( 'Could not set embroidery BMP resolution.' );
				}
				try {
					if ( 0 !== fseek( $output, 38 ) || 8 !== fwrite( $output, pack( 'V2', 5669, 5669 ) ) || ! fflush( $output ) ) {
						throw new \RuntimeException( 'Could not set embroidery BMP resolution.' );
					}
				} finally {
					fclose( $output );
				}
			} finally {
				imagedestroy( $renderer->image );
			}
		} finally {
			fclose( $stream );
		}
	}

	private function __construct( int $width, int $height ) {
		if ( min( $width, $height ) < 1 || max( $width, $height ) > 4096 ) {
			throw new \RuntimeException( 'Embroidery artwork exceeds the BMP rendering size limit.' );
		}
		$this->width  = $width;
		$this->height = $height;
		$this->matrix = [ 2.0, 0.0, 0.0, -2.0, 0.0, (float) $height ];
		$this->image  = imagecreatetruecolor( $width, $height );
		imagefilledrectangle( $this->image, 0, 0, $width - 1, $height - 1, 0xffffff );
	}

	/** Parse a bounded line of numeric geometry, never arbitrary PostScript. */
	private function draw( string $line ): void {
		$line = trim( $line );
		if ( '' === $line || '%' === $line[0] ) {
			return;
		}
		$tokens = preg_split( '/\s+/', str_replace( [ '[', ']' ], ' ', $line ) );
		$args   = [];
		if ( ! is_array( $tokens ) ) {
			throw new \RuntimeException( 'Could not parse embroidery drawing.' );
		}
		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}
			$this->budget();
			if ( is_numeric( $token ) ) {
				$value = (float) $token;
				if ( ! is_finite( $value ) || abs( $value ) > 1.0e9 || count( $args ) >= 6 ) {
					throw new \RuntimeException( 'Invalid embroidery drawing coordinate.' );
				}
				$args[] = $value;
				continue;
			}
			if ( count( $args ) !== ( self::OPERANDS[ $token ] ?? 0 ) ) {
				throw new \RuntimeException( 'Invalid embroidery drawing operands.' );
			}
			switch ( $token ) {
				case 'gsave':
					if ( count( $this->stack ) >= 128 ) {
						throw new \RuntimeException( 'Embroidery drawing nesting limit exceeded.' );
					}
					$this->stack[] = [ $this->matrix, $this->path, $this->clip, $this->colour, $this->line_width, $this->path_points ];
					break;
				case 'grestore':
					if ( [] === $this->stack ) {
						throw new \RuntimeException( 'Unbalanced embroidery drawing.' );
					}
					[ $this->matrix, $this->path, $this->clip, $this->colour, $this->line_width, $this->path_points ] = array_pop( $this->stack );
					break;
				case 'translate':
					$this->concat( [ 1, 0, 0, 1, $args[0], $args[1] ] );
					break;
				case 'scale':
					$this->concat( [ $args[0], 0, 0, $args[1], 0, 0 ] );
					break;
				case 'rotate':
					$angle = deg2rad( $args[0] );
					$this->concat( [ cos( $angle ), sin( $angle ), -sin( $angle ), cos( $angle ), 0, 0 ] );
					break;
				case 'concat':
					$this->concat( $args );
					break;
				case 'newpath':
					$this->path        = [];
					$this->path_points = 0;
					break;
				case 'moveto':
					$this->path[] = [];
					$this->add_point( $this->point( $args[0], $args[1] ) );
					break;
				case 'lineto':
					$this->add_point( $this->point( $args[0], $args[1] ) );
					break;
				case 'rlineto':
					$p = $this->current();
					$m = $this->matrix;
					$this->add_point( [ $p[0] + $m[0] * $args[0] + $m[2] * $args[1], $p[1] + $m[1] * $args[0] + $m[3] * $args[1] ] );
					break;
				case 'curveto':
					$this->curve( $this->current(), $this->point( $args[0], $args[1] ), $this->point( $args[2], $args[3] ), $this->point( $args[4], $args[5] ) );
					break;
				case 'arc':
					if ( 0.0 !== $args[3] || 360.0 !== $args[4] ) {
						throw new \RuntimeException( 'Unsupported embroidery arc.' );
					}
					$this->path[] = [];
					for ( $i = 0; $i <= 256; $i++ ) {
						$angle = $i * 2 * M_PI / 256;
						$this->add_point( $this->point( $args[0] + $args[2] * cos( $angle ), $args[1] + $args[2] * sin( $angle ) ) );
					}
					break;
				case 'closepath':
					$this->current();
					$this->add_point( $this->path[ count( $this->path ) - 1 ][0] );
					break;
				case 'setrgbcolor':
					$rgb          = array_map( static fn( float $v ): int => (int) round( max( 0, min( 1, $v ) ) * 255 ), $args );
					$this->colour = ( $rgb[0] << 16 ) | ( $rgb[1] << 8 ) | $rgb[2];
					break;
				case 'setlinewidth':
					$this->line_width = max( 0.0, $args[0] );
					break;
				case 'rectfill':
					[ $x, $y, $w, $h ] = $args;
					$this->paint( [ [ $this->point( $x, $y ), $this->point( $x + $w, $y ), $this->point( $x + $w, $y + $h ), $this->point( $x, $y + $h ) ] ] );
					break;
				case 'fill':
				case 'eofill':
					$this->paint( $this->path, 'eofill' === $token );
					$this->path        = [];
					$this->path_points = 0;
					break;
				case 'clip':
					$this->clip = $this->spans( $this->path );
					break;
				case 'stroke':
					$this->stroke();
					$this->path        = [];
					$this->path_points = 0;
					break;
				case 'showpage':
					break;
				default:
					throw new \RuntimeException( esc_html__( 'This artwork uses drawing features unavailable in the PHP embroidery renderer. Install Ghostscript or use a PNG version of the artwork.', 'overcustomise' ) );
			}
			$args = [];
		}
		if ( [] !== $args ) {
			throw new \RuntimeException( 'Incomplete embroidery drawing operands.' );
		}
	}

	private function budget(): void {
		if ( ++$this->work > 20000000 ) {
			throw new \RuntimeException( 'Embroidery artwork exceeds the PHP rendering work limit.' );
		}
	}

	private function concat( array $n ): void {
		$m            = $this->matrix;
		$this->matrix = [ $m[0] * $n[0] + $m[2] * $n[1], $m[1] * $n[0] + $m[3] * $n[1], $m[0] * $n[2] + $m[2] * $n[3], $m[1] * $n[2] + $m[3] * $n[3], $m[0] * $n[4] + $m[2] * $n[5] + $m[4], $m[1] * $n[4] + $m[3] * $n[5] + $m[5] ];
		foreach ( $this->matrix as $value ) {
			if ( ! is_finite( $value ) || abs( $value ) > 1.0e9 ) {
				throw new \RuntimeException( 'Embroidery transform exceeds the rendering limit.' );
			}
		}
	}

	private function point( float $x, float $y ): array {
		$m = $this->matrix;
		return [ $m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5] ];
	}

	private function current(): array {
		$path = end( $this->path );
		if ( ! $path ) {
			throw new \RuntimeException( 'Embroidery drawing has no current point.' );
		}
		return $path[ count( $path ) - 1 ];
	}

	private function add_point( array $point ): void {
		$this->budget();
		if ( ++$this->path_points > 100000 ) {
			throw new \RuntimeException( 'Embroidery path exceeds the PHP rendering point limit.' );
		}
		if ( [] === $this->path ) {
			throw new \RuntimeException( 'Embroidery drawing has no current path.' );
		}
		$this->path[ count( $this->path ) - 1 ][] = $point;
	}

	/** Subdivide curves in device space to a quarter-pixel tolerance. */
	private function curve( array $a, array $b, array $c, array $d, int $depth = 0 ): void {
		$this->budget();
		$error = max( abs( 3 * $b[0] - 2 * $a[0] - $d[0] ), abs( 3 * $b[1] - 2 * $a[1] - $d[1] ), abs( 3 * $c[0] - 2 * $d[0] - $a[0] ), abs( 3 * $c[1] - 2 * $d[1] - $a[1] ) );
		if ( $error <= 0.25 || $depth >= 16 ) {
			$this->add_point( $d );
			return;
		}
		$mid    = static fn( array $p, array $q ): array => [ ( $p[0] + $q[0] ) / 2, ( $p[1] + $q[1] ) / 2 ];
		$ab     = $mid( $a, $b );
		$bc     = $mid( $b, $c );
		$cd     = $mid( $c, $d );
		$abc    = $mid( $ab, $bc );
		$bcd    = $mid( $bc, $cd );
		$centre = $mid( $abc, $bcd );
		$this->curve( $a, $ab, $abc, $centre, $depth + 1 );
		$this->curve( $centre, $bcd, $cd, $d, $depth + 1 );
	}

	/** Scanline winding preserves holes in glyphs and SVG compound paths. */
	private function spans( array $paths, bool $even_odd = false ): array {
		$rows          = [];
		$intersections = 0;
		foreach ( $paths as $points ) {
			$count = count( $points );
			for ( $i = 0; $i < $count; $i++ ) {
				$a = $points[ $i ];
				$b = $points[ ( $i + 1 ) % $count ];
				if ( abs( $a[1] - $b[1] ) < 1.0e-10 ) {
					continue;
				}
				$first = (int) max( 0, min( $this->height, ceil( min( $a[1], $b[1] ) - 0.5 ) ) );
				$last  = (int) max( -1, min( $this->height - 1, ceil( max( $a[1], $b[1] ) - 0.5 ) - 1 ) );
				for ( $y = $first; $y <= $last; $y++ ) {
					$this->budget();
					if ( ++$intersections > 131072 ) {
						throw new \RuntimeException( 'Embroidery path exceeds the PHP rendering memory limit.' );
					}
					$rows[ $y ][] = [ $a[0] + ( $y + 0.5 - $a[1] ) * ( $b[0] - $a[0] ) / ( $b[1] - $a[1] ), $b[1] > $a[1] ? 1 : -1 ];
				}
			}
		}
		$result = [];
		foreach ( $rows as $y => $crossings ) {
			usort( $crossings, static fn( array $a, array $b ): int => $a[0] <=> $b[0] );
			$winding = 0;
			$start   = 0.0;
			foreach ( $crossings as [ $x, $direction ] ) {
				$before   = $even_odd ? abs( $winding ) % 2 : $winding;
				$winding += $direction;
				$after    = $even_odd ? abs( $winding ) % 2 : $winding;
				if ( 0 === $before && 0 !== $after ) {
					$start = $x;
				} elseif ( 0 !== $before && 0 === $after ) {
					$left  = (int) max( 0, min( $this->width, ceil( $start - 0.5 ) ) );
					$right = (int) max( -1, min( $this->width - 1, ceil( $x - 0.5 ) - 1 ) );
					$clips = null === $this->clip ? [ [ 0, $this->width - 1 ] ] : ( $this->clip[ $y ] ?? [] );
					foreach ( $clips as [ $cl, $cr ] ) {
						if ( max( $left, $cl ) <= min( $right, $cr ) ) {
							$result[ $y ][] = [ max( $left, $cl ), min( $right, $cr ) ];
						}
					}
				}
			}
		}
		return $result;
	}

	private function paint( array $paths, bool $even_odd = false ): void {
		foreach ( $this->spans( $paths, $even_odd ) as $y => $spans ) {
			foreach ( $spans as [ $left, $right ] ) {
				imagefilledrectangle( $this->image, $left, $y, $right, $y, $this->colour );
			}
		}
	}

	/** Stroke in user space before transforming, including non-uniform SVG scales. */
	private function stroke(): void {
		$m   = $this->matrix;
		$det = $m[0] * $m[3] - $m[1] * $m[2];
		if ( abs( $det ) < 1.0e-12 ) {
			return;
		}
		$half = max( 0.01, $this->line_width / 2 );
		foreach ( $this->path as $points ) {
			$local    = array_map(
				static function ( array $p ) use ( $m, $det ): array {
					$x = $p[0] - $m[4];
					$y = $p[1] - $m[5];
					return [ ( $m[3] * $x - $m[2] * $y ) / $det, ( -$m[1] * $x + $m[0] * $y ) / $det ];
				},
				$points
			);
			$segments = [];
			$count    = count( $local );
			for ( $i = 1; $i < $count; $i++ ) {
				$a      = $local[ $i - 1 ];
				$b      = $local[ $i ];
				$length = hypot( $b[0] - $a[0], $b[1] - $a[1] );
				if ( $length < 1.0e-10 ) {
					continue;
				}
				$n = [ -( $b[1] - $a[1] ) / $length * $half, ( $b[0] - $a[0] ) / $length * $half ];
				$this->paint( [ [ $this->point( $a[0] + $n[0], $a[1] + $n[1] ), $this->point( $b[0] + $n[0], $b[1] + $n[1] ), $this->point( $b[0] - $n[0], $b[1] - $n[1] ), $this->point( $a[0] - $n[0], $a[1] - $n[1] ) ] ] );
				$segments[] = [ $a, $b, $n ];
			}
			$closed = $count > 2 && end( $local ) === $local[0];
			$joins  = count( $segments ) - ( $closed ? 0 : 1 );
			for ( $i = 0; $i < $joins; $i++ ) {
				[ , $p, $n ] = $segments[ $i ];
				$q           = $segments[ ( $i + 1 ) % count( $segments ) ][2];
				$den         = 1 + ( $n[0] * $q[0] + $n[1] * $q[1] ) / ( $half * $half );
				foreach ( [ -1, 1 ] as $side ) {
					$join = [ $this->point( $p[0], $p[1] ), $this->point( $p[0] + $side * $n[0], $p[1] + $side * $n[1] ) ];
					if ( $den > 0.02 ) {
						$join[] = $this->point( $p[0] + $side * ( $n[0] + $q[0] ) / $den, $p[1] + $side * ( $n[1] + $q[1] ) / $den );
					}
					$join[] = $this->point( $p[0] + $side * $q[0], $p[1] + $side * $q[1] );
					$this->paint( [ $join ] );
				}
			}
		}
	}
}
