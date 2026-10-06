<?php
/**
 * Bounded, flat-colour tracing for AI artwork approved in the customiser.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Artwork_Vectoriser {

	private const MAX_EDGE   = 1024;
	private const MAX_EDGES  = 200000;
	private const MAX_POINTS = 24000;
	private const MAX_CONTOURS = 256;

	/** Trace once, before approval. The returned SVG is both preview and production artwork. */
	public static function trace( string $bytes, int $colours, bool $remove_background ): string {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			throw new \RuntimeException( __( 'Vector artwork processing requires PHP GD.', 'overcustomise' ) );
		}
		if ( $colours < 1 || $colours > 32 ) {
			throw new \RuntimeException( __( 'Choose between 1 and 32 vector colours.', 'overcustomise' ) );
		}
		$info = @getimagesizefromstring( $bytes );
		if ( ! is_array( $info ) || $info[0] * $info[1] > 16000000 || strlen( $bytes ) > 20971520 ) {
			throw new \RuntimeException( __( 'The image is too large to trace.', 'overcustomise' ) );
		}
		$source = @imagecreatefromstring( $bytes );
		if ( ! $source ) {
			throw new \RuntimeException( __( 'The image could not be opened for tracing.', 'overcustomise' ) );
		}
		$scale = min( 1, self::MAX_EDGE / max( $info[0], $info[1] ) );
		$w = max( 1, (int) round( $info[0] * $scale ) );
		$h = max( 1, (int) round( $info[1] * $scale ) );
		$image = imagecreatetruecolor( $w, $h );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagecopyresampled( $image, $source, 0, 0, 0, 0, $w, $h, $info[0], $info[1] );
		imagedestroy( $source );
		try {
			$mask = self::background_mask( $image, $w, $h, $remove_background );
			// EPS has no soft alpha. Resolve it once against the white artwork proof,
			// before palette reduction, rather than discarding faint pixels on export.
			for ( $y = 0; $y < $h; $y++ ) {
				for ( $x = 0; $x < $w; $x++ ) {
					$rgba = imagecolorat( $image, $x, $y );
					$alpha = ( ( $rgba >> 24 ) & 127 ) / 127;
					$rgb = 0;
					foreach ( [ 16, 8, 0 ] as $shift ) {
						$rgb |= (int) round( ( ( $rgba >> $shift ) & 255 ) * ( 1 - $alpha ) + 255 * $alpha ) << $shift;
					}
					imagesetpixel( $image, $x, $y, $rgb );
				}
			}
			// Quantise only foreground samples: a large white background must not
			// consume one of the requested thread colours or dominate the palette.
			$samples = imagecreatetruecolor( $w, $h );
			$visible = [];
			for ( $i = 0; $i < $w * $h; $i++ ) {
				if ( "\0" === $mask[$i] ) {
					$visible[] = $i;
				}
			}
			if ( ! $visible ) {
				imagedestroy( $samples );
				throw new \RuntimeException( __( 'No visible artwork remains to trace.', 'overcustomise' ) );
			}
			$count = count( $visible );
			for ( $i = 0; $i < $w * $h; $i++ ) {
				$from = $visible[$i % $count];
				imagesetpixel( $samples, $i % $w, intdiv( $i, $w ), imagecolorat( $image, $from % $w, intdiv( $from, $w ) ) );
			}
			unset( $visible );
			$true_samples = imagecreatetruecolor( $w, $h );
			imagecopy( $true_samples, $samples, 0, 0, 0, 0, $w, $h );
			imagetruecolortopalette( $samples, false, $colours );
			imagecolormatch( $true_samples, $samples );
			imagedestroy( $true_samples );
			$palette = [];
			for ( $i = 0; $i < imagecolorstotal( $samples ); $i++ ) {
				$c = imagecolorsforindex( $samples, $i );
				$palette[] = [ $c['red'], $c['green'], $c['blue'] ];
			}
			imagedestroy( $samples );
			$labels = str_repeat( "\xff", $w * $h );
			$cache = [];
			for ( $i = 0; $i < $w * $h; $i++ ) {
				if ( "\0" !== $mask[$i] ) {
					continue;
				}
				$rgb = imagecolorat( $image, $i % $w, intdiv( $i, $w ) );
				// A bounded 5-bit lookup keeps noisy generated images inexpensive.
				$key = ( $rgb & 0xf80000 ) | ( $rgb & 0x00f800 ) | ( $rgb & 0x0000f8 );
				if ( ! isset( $cache[$key] ) ) {
					$best = 0;
					$distance = PHP_INT_MAX;
					foreach ( $palette as $index => $c ) {
						$d = ( ( $rgb >> 16 & 255 ) - $c[0] ) ** 2 + ( ( $rgb >> 8 & 255 ) - $c[1] ) ** 2 + ( ( $rgb & 255 ) - $c[2] ) ** 2;
						if ( $d < $distance ) {
							$distance = $d;
							$best = $index;
						}
					}
					$cache[$key] = chr( $best );
				}
				$labels[$i] = $cache[$key];
			}
			$labels = self::despeckle( $labels, $w, $h );
			$labels = self::merge_small_regions( $labels, $w, $h );
			return self::outlines( $labels, $w, $h, $palette );
		} finally {
			imagedestroy( $image );
		}
	}

	/** Remove only edge-connected plain background, preserving white enclosed details. */
	private static function background_mask( $image, int $w, int $h, bool $remove ): string {
		$mask = str_repeat( "\0", $w * $h );
		for ( $i = 0; $i < $w * $h; $i++ ) {
			if ( ( imagecolorat( $image, $i % $w, intdiv( $i, $w ) ) >> 24 & 127 ) >= 124 ) {
				$mask[$i] = "\1";
			}
		}
		if ( ! $remove ) {
			return $mask;
		}
		$queue = new \SplQueue();
		$visited = str_repeat( "\0", $w * $h );
		$corners = [ 0, $w - 1, ( $h - 1 ) * $w, $w * $h - 1 ];
		$references = array_map( static fn ( $i ) => imagecolorat( $image, $i % $w, intdiv( $i, $w ) ), $corners );
		if ( count( array_filter( $references, static fn ( $rgba ) => ( $rgba >> 24 & 127 ) >= 124 ) ) >= 3 ) {
			return $mask;
		}
		$reference = null;
		foreach ( $references as $candidate ) {
			$matches = array_filter( $references, static fn ( $rgb ) => self::colour_distance( $rgb, $candidate ) <= 16 && ( $rgb >> 24 & 127 ) < 124 );
			if ( count( $matches ) >= 3 ) {
				$reference = $candidate;
				break;
			}
		}
		if ( null === $reference ) {
			throw new \RuntimeException( __( 'Vector background removal needs a plain, consistent background.', 'overcustomise' ) );
		}
		foreach ( $corners as $corner ) {
			$queue->enqueue( $corner );
			while ( ! $queue->isEmpty() ) {
				$i = $queue->dequeue();
				if ( "\0" !== $visited[$i] ) {
					continue;
				}
				$rgb = imagecolorat( $image, $i % $w, intdiv( $i, $w ) );
				$distance = self::colour_distance( $rgb, $reference );
				if ( "\1" !== $mask[$i] && $distance > 16 ) {
					continue;
				}
				$visited[$i] = "\1";
				$mask[$i] = "\1";
				foreach ( self::neighbours( $i, $w, $h ) as $next ) {
					if ( "\0" === $visited[$next] ) {
						$queue->enqueue( $next );
					}
				}
			}
		}
		return $mask;
	}

	/** Maximum channel difference for the plain-background flood fill. */
	private static function colour_distance( int $a, int $b ): int {
		return max( abs( ( $a >> 16 & 255 ) - ( $b >> 16 & 255 ) ), abs( ( $a >> 8 & 255 ) - ( $b >> 8 & 255 ) ), abs( ( $a & 255 ) - ( $b & 255 ) ) );
	}

	/** Four-connected neighbours, without wrapping across image rows. */
	private static function neighbours( int $i, int $w, int $h ): array {
		$result = [];
		if ( $i % $w > 0 ) {
			$result[] = $i - 1;
		}
		if ( $i % $w < $w - 1 ) {
			$result[] = $i + 1;
		}
		if ( $i >= $w ) {
			$result[] = $i - $w;
		}
		if ( $i < $w * ( $h - 1 ) ) {
			$result[] = $i + $w;
		}
		return $result;
	}

	/** Remove isolated colour noise without expanding into transparent artwork margins. */
	private static function despeckle( string $labels, int $w, int $h ): string {
		$output = $labels;
		for ( $y = 1; $y < $h - 1; $y++ ) {
			for ( $x = 1; $x < $w - 1; $x++ ) {
				$i = $y * $w + $x;
				if ( "\xff" === $labels[ $i ] ) {
					continue;
				}
				$counts = [];
				foreach ( [ -$w - 1, -$w, -$w + 1, -1, 0, 1, $w - 1, $w, $w + 1 ] as $offset ) {
					$colour = ord( $labels[$i + $offset] );
					$counts[$colour] = ( $counts[$colour] ?? 0 ) + 1;
				}
				if ( isset( $counts[255] ) || $counts[ ord( $labels[ $i ] ) ] >= 3 ) {
					continue;
				}
				arsort( $counts );
				$majority = array_key_first( $counts );
				if ( $counts[ $majority ] >= 5 ) {
					$output[ $i ] = chr( $majority );
				}
			}
		}
		return $output;
	}

	/** Merge tiny connected colour regions, including anti-aliased edge fragments. */
	private static function merge_small_regions( string $labels, int $w, int $h ): string {
		// Scale with working resolution; retain the existing four-pixel floor for
		// small images. Merge pixels before tracing so adjoining fills have no gaps.
		$minimum = max( 4, (int) round( 24 * ( max( $w, $h ) / self::MAX_EDGE ) ** 2 ) );
		$visited = str_repeat( "\0", $w * $h );
		$output  = $labels;
		$queue   = new \SplQueue();
		for ( $start = 0; $start < $w * $h; $start++ ) {
			if ( "\0" !== $visited[ $start ] || "\xff" === $labels[ $start ] ) {
				continue;
			}
			$colour = $labels[ $start ];
			$visited[ $start ] = "\1";
			$queue->enqueue( $start );
			$pixels = [];
			$count = 0;
			$boundary = [];
			while ( ! $queue->isEmpty() ) {
				$i = $queue->dequeue();
				if ( ++$count < $minimum ) {
					$pixels[] = $i;
				}
				foreach ( self::neighbours( $i, $w, $h ) as $next ) {
					if ( $labels[ $next ] === $colour ) {
						if ( "\0" === $visited[ $next ] ) {
							$visited[ $next ] = "\1";
							$queue->enqueue( $next );
						}
					} elseif ( "\xff" !== $labels[ $next ] ) {
						$adjacent = ord( $labels[ $next ] );
						$boundary[ $adjacent ] = ( $boundary[ $adjacent ] ?? 0 ) + 1;
					}
				}
			}
			if ( $count >= $minimum ) {
				continue;
			}
			arsort( $boundary );
			$replacement = $boundary ? chr( array_key_first( $boundary ) ) : "\xff";
			foreach ( $pixels as $i ) {
				$output[ $i ] = $replacement;
			}
		}
		return $output;
	}

	/** Trace region boundaries (including holes), never pixel rectangles. */
	private static function outlines( string $labels, int $w, int $h, array $palette ): string {
		$svg = sprintf( '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">', $w, $h, $w, $h );
		$points = 0;
		$paths = 0;
		$contours = 0;
		foreach ( $palette as $index => $rgb ) {
			$colour = chr( $index );
			$edges = [];
			$edge_count = 0;
			for ( $y = 0; $y < $h; $y++ ) {
				for ( $x = 0; $x < $w; $x++ ) {
					$i = $y * $w + $x;
					if ( $labels[ $i ] !== $colour ) {
						continue;
					}
					$v = $y * ( $w + 1 ) + $x;
					foreach ( [
						[ 0 === $y || $labels[$i - $w] !== $colour, $v, 0 ],
						[ $x === $w - 1 || $labels[$i + 1] !== $colour, $v + 1, 1 ],
						[ $y === $h - 1 || $labels[$i + $w] !== $colour, $v + $w + 2, 2 ],
						[ 0 === $x || $labels[$i - 1] !== $colour, $v + $w + 1, 3 ],
					] as [ $boundary, $vertex, $direction ] ) {
						if ( $boundary ) {
							$edges[$vertex] = ( $edges[$vertex] ?? 0 ) | ( 1 << $direction );
							if ( ++$edge_count > self::MAX_EDGES ) {
								throw new \RuntimeException( __( 'The artwork is too detailed to trace. Use a simpler image or fewer colours.', 'overcustomise' ) );
							}
						}
					}
				}
			}
			$d = '';
			$steps = [ 1, $w + 1, -1, -$w - 1 ];
			while ( $edges ) {
				$start = array_key_first( $edges );
				$v = $start;
				$direction = 0;
				$ring = [];
				do {
					$ring[] = [ $v % ( $w + 1 ), intdiv( $v, $w + 1 ) ];
					// Prefer the right turn at diagonal junctions so touching islands
					// remain separate closed contours, including transparent holes.
					foreach ( [ ( $direction + 1 ) % 4, $direction, ( $direction + 3 ) % 4, ( $direction + 2 ) % 4 ] as $next ) {
						if ( ( $edges[ $v ] ?? 0 ) & ( 1 << $next ) ) {
							$direction = $next;
							break;
						}
					}
					$edges[$v] &= ~( 1 << $direction );
					if ( ! $edges[ $v ] ) {
						unset( $edges[ $v ] );
					}
					$v += $steps[$direction];
				} while ( $v !== $start );
				$area = 0;
				$last = $ring[count( $ring ) - 1];
				foreach ( $ring as $p ) {
					$area += $last[0] * $p[1] - $p[0] * $last[1];
					$last = $p;
				}
				// Ignore sub-four-pixel islands/holes instead of exporting speckle objects.
				if ( abs( $area ) < 8 ) {
					continue;
				}
				if ( ++$contours > self::MAX_CONTOURS ) {
					throw new \RuntimeException( __( 'The traced artwork contains too many separate shapes. Use a simpler image or fewer colours.', 'overcustomise' ) );
				}
				$ring[] = $ring[0];
				$middle = intdiv( count( $ring ), 2 );
				$ring = array_merge( self::simplify( array_slice( $ring, 0, $middle + 1 ) ), array_slice( self::simplify( array_slice( $ring, $middle ) ), 1 ) );
				$points += count( $ring );
				if ( $points > self::MAX_POINTS ) {
					throw new \RuntimeException( __( 'The traced artwork has too many outlines. Use a simpler image or fewer colours.', 'overcustomise' ) );
				}
				foreach ( $ring as $j => $p ) {
					$d .= ( 0 === $j ? 'M' : 'L' ) . $p[0] . ' ' . $p[1];
				}
				$d .= 'Z';
			}
			if ( '' !== $d ) {
				$svg .= sprintf( '<path fill="#%02x%02x%02x" fill-rule="evenodd" d="%s"/>', $rgb[0], $rgb[1], $rgb[2], $d );
				$paths++;
			}
		}
		if ( ! $paths ) {
			throw new \RuntimeException( __( 'No printable shapes remain after tracing.', 'overcustomise' ) );
		}
		return $svg . '</svg>';
	}

	/** Iterative Douglas-Peucker simplification with sub-pixel error tolerance. */
	private static function simplify( array $points ): array {
		$last = count( $points ) - 1;
		$keep = [ 0 => true, $last => true ];
		$stack = [ [ 0, $last ] ];
		while ( $stack ) {
			[ $a, $b ] = array_pop( $stack );
			$dx = $points[$b][0] - $points[$a][0];
			$dy = $points[$b][1] - $points[$a][1];
			$length = $dx * $dx + $dy * $dy;
			$max = 0.65 ** 2;
			$split = null;
			for ( $i = $a + 1; $i < $b; $i++ ) {
				$t = $length ? max( 0, min( 1, ( ( $points[$i][0] - $points[$a][0] ) * $dx + ( $points[$i][1] - $points[$a][1] ) * $dy ) / $length ) ) : 0;
				$d = ( $points[$i][0] - $points[$a][0] - $t * $dx ) ** 2 + ( $points[$i][1] - $points[$a][1] - $t * $dy ) ** 2;
				if ( $d > $max ) {
					$max = $d;
					$split = $i;
				}
			}
			if ( null !== $split ) {
				$keep[$split] = true;
				$stack[] = [ $a, $split ];
				$stack[] = [ $split, $b ];
			}
		}
		ksort( $keep );
		return array_values( array_intersect_key( $points, $keep ) );
	}
}
