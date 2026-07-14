/* eslint-disable no-undef, @wordpress/no-unused-vars-before-return */

import { createFont } from 'fonteditor-core';

import { displayBounds } from '../../shared/render-math';

const cartSerializationMethods = {
	// ── Cart serialisation ────────────────────────────────────────────────────────

	async captureAreaSnapshots() {
		const snapshots = {};
		for ( const [ areaIndex, area ] of this.areas.entries() ) {
			const canvas = this.canvases[ areaIndex ];
			const bounds = this.areaBounds( area );
			const display = displayBounds( bounds );
			const scale = canvas?._ocScaleX || 1;
			if (
				! canvas ||
				! display?.w ||
				! display?.h ||
				typeof canvas.toSVG !== 'function'
			) {
				continue;
			}

			await this.redraw( areaIndex, {
				renderGroup: false,
				pushGallery: false,
			} );

			const objects = canvas.getObjects ? canvas.getObjects() : [];
			const imageSources = objects
				.filter(
					( obj ) => obj._ocContent === true && obj._ocSourceUrl
				)
				.map( ( obj ) => ( {
					url: obj._ocSourceUrl,
					color: obj._ocSnapshotColor || '',
				} ) );
			const previousExportFlags = objects.map( ( obj ) => [
				obj,
				obj.excludeFromExport,
			] );
			objects.forEach( ( obj ) => {
				obj.excludeFromExport = obj._ocContent !== true;
			} );

			try {
				let svg = canvas.toSVG( {
					width: Math.max(
						1,
						Math.round( Number( display.w ) * scale )
					),
					height: Math.max(
						1,
						Math.round( Number( display.h ) * scale )
					),
					viewBox: {
						x: Number( display.x || 0 ) * scale,
						y: Number( display.y || 0 ) * scale,
						width: Math.max( 1, Number( display.w ) * scale ),
						height: Math.max( 1, Number( display.h ) * scale ),
					},
				} );
				svg = await this.outlineSnapshotText( svg );
				svg = await this.inlineSnapshotSvgImages( svg, imageSources );
				if ( svg && svg.includes( '<svg' ) ) {
					snapshots[ area.id || area.areaId || areaIndex ] = {
						format: 'fabric-svg-v1',
						unit: 'mockup_px',
						scale,
						svg,
					};
				}
			} catch {
				// Snapshot export is best-effort; PHP generation keeps the layer fallback.
			} finally {
				previousExportFlags.forEach( ( [ obj, flag ] ) => {
					obj.excludeFromExport = flag;
				} );
			}
		}

		await this.redraw( this.activeArea );

		return snapshots;
	},

	async inlineSnapshotSvgImages( svg, imageSources = [] ) {
		if ( ! svg || ! svg.includes( '<image' ) || ! imageSources.length ) {
			return svg;
		}

		const doc = new DOMParser().parseFromString( svg, 'image/svg+xml' );
		const svgEl = doc.documentElement;
		if ( ! svgEl || svgEl.nodeName.toLowerCase() !== 'svg' ) {
			return svg;
		}

		const imageNodes = Array.from( svgEl.querySelectorAll( 'image' ) );
		for ( let index = 0; index < imageNodes.length; index++ ) {
			const source = imageSources[ index ];
			if ( ! source?.url ) {
				continue;
			}

			let sourceSvg = '';
			try {
				sourceSvg = await this.svgSourceForSnapshotImage( source.url );
			} catch {
				continue;
			}
			if ( ! sourceSvg ) {
				continue;
			}

			const sourceDoc = new DOMParser().parseFromString(
				sourceSvg,
				'image/svg+xml'
			);
			const sourceEl = sourceDoc.documentElement;
			if ( ! sourceEl || sourceEl.nodeName.toLowerCase() !== 'svg' ) {
				continue;
			}

			if ( source.color ) {
				this.flattenSnapshotPatternPaint( sourceEl, source.color );
			}

			const replacement = this.snapshotImageReplacementGroup(
				doc,
				imageNodes[ index ],
				sourceEl
			);
			if ( replacement ) {
				imageNodes[ index ].replaceWith( replacement );
			}
		}

		return new XMLSerializer().serializeToString( svgEl );
	},

	async svgSourceForSnapshotImage( url ) {
		const value = String( url || '' );
		if ( value.startsWith( 'data:image/svg+xml' ) ) {
			const payload = value.slice( value.indexOf( ',' ) + 1 );
			return decodeURIComponent( payload );
		}

		const cleanUrl = value.split( '?' )[ 0 ].toLowerCase();
		if ( ! cleanUrl.endsWith( '.svg' ) ) {
			return '';
		}

		const response = await fetch( value, {
			credentials: 'same-origin',
			cache: 'force-cache',
		} );
		if ( ! response.ok ) {
			return '';
		}
		return response.text();
	},

	flattenSnapshotPatternPaint( element, color ) {
		Array.from( element.querySelectorAll( '[fill], [stroke]' ) ).forEach(
			( node ) => {
				[ 'fill', 'stroke' ].forEach( ( attr ) => {
					const value = String(
						node.getAttribute( attr ) || ''
					).trim();
					if ( /^url\(/i.test( value ) ) {
						node.setAttribute( attr, color );
					}
				} );
			}
		);
		Array.from( element.querySelectorAll( '[style]' ) ).forEach(
			( node ) => {
				node.setAttribute(
					'style',
					String( node.getAttribute( 'style' ) || '' ).replace(
						/\b(fill|stroke)\s*:\s*url\([^;)]+\)/gi,
						`$1:${ color }`
					)
				);
			}
		);
	},

	snapshotImageReplacementGroup( doc, imageNode, sourceEl ) {
		const viewBox = this.svgViewBoxValues( sourceEl );
		if ( ! viewBox ) {
			return null;
		}

		const [ vbX, vbY, vbW, vbH ] = viewBox;
		const imageX = this.svgNumber( imageNode.getAttribute( 'x' ), 0 );
		const imageY = this.svgNumber( imageNode.getAttribute( 'y' ), 0 );
		const imageW = this.svgNumber( imageNode.getAttribute( 'width' ), vbW );
		const imageH = this.svgNumber(
			imageNode.getAttribute( 'height' ),
			vbH
		);
		if ( vbW <= 0 || vbH <= 0 || imageW <= 0 || imageH <= 0 ) {
			return null;
		}

		const ns = 'http://www.w3.org/2000/svg';
		const outer = doc.createElementNS( ns, 'g' );
		[ 'transform', 'opacity', 'clip-path' ].forEach( ( attr ) => {
			if ( imageNode.hasAttribute( attr ) ) {
				outer.setAttribute( attr, imageNode.getAttribute( attr ) );
			}
		} );

		const inner = doc.createElementNS( ns, 'g' );
		inner.setAttribute(
			'transform',
			`translate(${ imageX } ${ imageY }) scale(${ imageW / vbW } ${
				imageH / vbH
			}) translate(${ -vbX } ${ -vbY })`
		);

		Array.from( sourceEl.childNodes ).forEach( ( child ) => {
			inner.appendChild( doc.importNode( child, true ) );
		} );
		outer.appendChild( inner );

		return outer;
	},

	svgViewBoxValues( svgEl ) {
		const viewBox = String( svgEl.getAttribute( 'viewBox' ) || '' ).trim();
		if ( viewBox ) {
			const parts = viewBox.split( /[\s,]+/ ).map( Number );
			if ( parts.length >= 4 && parts.every( Number.isFinite ) ) {
				return parts.slice( 0, 4 );
			}
		}

		const width = this.svgNumber( svgEl.getAttribute( 'width' ), 0 );
		const height = this.svgNumber( svgEl.getAttribute( 'height' ), 0 );
		return width > 0 && height > 0 ? [ 0, 0, width, height ] : null;
	},

	async outlineSnapshotText( svg ) {
		if ( ! svg || ! svg.includes( '<text' ) ) {
			return svg;
		}

		const doc = new DOMParser().parseFromString( svg, 'image/svg+xml' );
		const svgEl = doc.documentElement;
		if ( ! svgEl || svgEl.nodeName.toLowerCase() !== 'svg' ) {
			return svg;
		}

		const textNodes = Array.from( svgEl.querySelectorAll( 'text' ) );
		for ( const textNode of textNodes ) {
			const fontFamily = this.cleanSvgFontFamily(
				textNode.getAttribute( 'font-family' ) ||
					textNode.style?.fontFamily ||
					''
			);
			const font = this.fonts.find(
				( item ) => item.name === fontFamily
			);
			if ( ! font ) {
				continue;
			}

			let outlineFont = null;
			try {
				outlineFont = await this.loadOutlineFont( font );
			} catch {
				continue;
			}

			const pathData = this.svgTextNodeToPathData(
				textNode,
				outlineFont
			);
			if ( ! pathData ) {
				continue;
			}

			const path = doc.createElementNS(
				'http://www.w3.org/2000/svg',
				'path'
			);
			path.setAttribute( 'd', pathData );
			[
				'fill',
				'stroke',
				'stroke-width',
				'opacity',
				'fill-opacity',
				'stroke-opacity',
				'transform',
			].forEach( ( attr ) => {
				if ( textNode.hasAttribute( attr ) ) {
					path.setAttribute( attr, textNode.getAttribute( attr ) );
				}
			} );
			if ( ! path.hasAttribute( 'fill' ) ) {
				path.setAttribute( 'fill', '#000000' );
			}
			textNode.replaceWith( path );
		}

		return new XMLSerializer().serializeToString( svgEl );
	},

	cleanSvgFontFamily( value ) {
		return String( value || '' )
			.split( ',' )[ 0 ]
			.trim()
			.replace( /^['"]|['"]$/g, '' );
	},

	async loadOutlineFont( font ) {
		if ( ! font?.name || ! font?.url ) {
			throw new Error( 'Missing font.' );
		}
		if ( this.outlineFontCache[ font.name ] ) {
			return this.outlineFontCache[ font.name ];
		}

		this.outlineFontCache[ font.name ] = fetch( font.url, {
			credentials: 'same-origin',
			cache: 'force-cache',
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Font download failed.' );
				}
				return response.arrayBuffer();
			} )
			.then( ( buffer ) => {
				const parsed = createFont( buffer, {
					type: this.fontTypeFromUrl( font.url ),
					compound2simple: true,
				} ).get();
				return {
					font,
					ttf: parsed,
					unitsPerEm: Number( parsed?.head?.unitsPerEm ) || 1000,
					glyphs: this.glyphMapForFont( parsed ),
				};
			} )
			.catch( ( err ) => {
				delete this.outlineFontCache[ font.name ];
				throw err;
			} );

		return this.outlineFontCache[ font.name ];
	},

	fontTypeFromUrl( url ) {
		const cleanUrl = String( url || '' )
			.split( '?' )[ 0 ]
			.toLowerCase();
		const ext = cleanUrl.split( '.' ).pop();
		return [ 'ttf', 'otf', 'woff', 'woff2', 'eot', 'svg' ].includes( ext )
			? ext
			: 'ttf';
	},

	glyphMapForFont( parsed ) {
		const glyphs = {};
		( parsed?.glyf || [] ).forEach( ( glyph ) => {
			( glyph.unicode || [] ).forEach( ( code ) => {
				glyphs[ code ] = glyph;
			} );
		} );
		return glyphs;
	},

	svgTextNodeToPathData( textNode, outlineFont ) {
		const chunks = this.svgTextChunks( textNode );
		if ( ! chunks.length ) {
			return '';
		}

		return chunks
			.map( ( chunk ) =>
				this.textChunkToPathData( chunk, textNode, outlineFont )
			)
			.filter( Boolean )
			.join( ' ' );
	},

	svgTextChunks( textNode ) {
		const tspans = Array.from( textNode.querySelectorAll( 'tspan' ) );
		if ( tspans.length ) {
			return tspans
				.map( ( tspan ) => ( {
					text: tspan.textContent || '',
					x: this.svgNumber(
						tspan.getAttribute( 'x' ),
						this.svgNumber( textNode.getAttribute( 'x' ), 0 )
					),
					y: this.svgNumber(
						tspan.getAttribute( 'y' ),
						this.svgNumber( textNode.getAttribute( 'y' ), 0 )
					),
					fontSize: this.svgNumber(
						tspan.getAttribute( 'font-size' ),
						this.svgNumber(
							textNode.getAttribute( 'font-size' ),
							16
						)
					),
					anchor:
						tspan.getAttribute( 'text-anchor' ) ||
						textNode.getAttribute( 'text-anchor' ) ||
						'start',
				} ) )
				.filter( ( chunk ) => chunk.text );
		}

		return [
			{
				text: textNode.textContent || '',
				x: this.svgNumber( textNode.getAttribute( 'x' ), 0 ),
				y: this.svgNumber( textNode.getAttribute( 'y' ), 0 ),
				fontSize: this.svgNumber(
					textNode.getAttribute( 'font-size' ),
					16
				),
				anchor: textNode.getAttribute( 'text-anchor' ) || 'start',
			},
		].filter( ( chunk ) => chunk.text );
	},

	textChunkToPathData( chunk, textNode, outlineFont ) {
		const scale = chunk.fontSize / outlineFont.unitsPerEm;
		const advance =
			this.textAdvanceWidth( chunk.text, outlineFont ) * scale;
		let cursor = chunk.x;
		const anchor = String( chunk.anchor || '' ).toLowerCase();
		if ( anchor === 'middle' ) {
			cursor -= advance / 2;
		}
		if ( anchor === 'end' ) {
			cursor -= advance;
		}

		const parts = [];
		for ( const char of Array.from( chunk.text ) ) {
			const glyph = outlineFont.glyphs[ char.codePointAt( 0 ) ];
			if ( glyph?.contours?.length ) {
				parts.push(
					this.glyphToSvgPath( glyph, cursor, chunk.y, scale )
				);
			}
			cursor +=
				( Number( glyph?.advanceWidth ) ||
					outlineFont.unitsPerEm * 0.5 ) * scale;
		}

		return parts.filter( Boolean ).join( ' ' );
	},

	textAdvanceWidth( text, outlineFont ) {
		return Array.from( text ).reduce( ( width, char ) => {
			const glyph = outlineFont.glyphs[ char.codePointAt( 0 ) ];
			return (
				width +
				( Number( glyph?.advanceWidth ) ||
					outlineFont.unitsPerEm * 0.5 )
			);
		}, 0 );
	},

	glyphToSvgPath( glyph, x, baseline, scale ) {
		return glyph.contours
			.map( ( contour ) =>
				this.contourToSvgPath( contour, x, baseline, scale )
			)
			.filter( Boolean )
			.join( ' ' );
	},

	contourToSvgPath( contour, x, baseline, scale ) {
		if ( ! contour?.length ) {
			return '';
		}
		const pointAt = ( index ) =>
			contour[ ( index + contour.length ) % contour.length ];
		const mid = ( a, b ) => ( {
			x: ( a.x + b.x ) / 2,
			y: ( a.y + b.y ) / 2,
			onCurve: true,
		} );
		const tx = ( point ) => Number( ( x + point.x * scale ).toFixed( 3 ) );
		const ty = ( point ) =>
			Number( ( baseline - point.y * scale ).toFixed( 3 ) );

		let startIndex = 0;
		let start = contour[ 0 ];
		const last = contour[ contour.length - 1 ];
		if ( ! start.onCurve ) {
			if ( last.onCurve ) {
				start = last;
				startIndex = contour.length - 1;
			} else {
				start = mid( last, start );
			}
		}

		const commands = [ `M${ tx( start ) } ${ ty( start ) }` ];
		let i = startIndex + 1;
		let processed = 0;
		while ( processed < contour.length ) {
			const p = pointAt( i );
			if ( p.onCurve ) {
				commands.push( `L${ tx( p ) } ${ ty( p ) }` );
				i += 1;
			} else {
				const next = pointAt( i + 1 );
				const end = next.onCurve ? next : mid( p, next );
				commands.push(
					`Q${ tx( p ) } ${ ty( p ) } ${ tx( end ) } ${ ty( end ) }`
				);
				i += next.onCurve ? 2 : 1;
			}
			processed = i - startIndex - 1;
		}

		commands.push( 'Z' );
		return commands.join( ' ' );
	},

	svgNumber( value, fallback = 0 ) {
		const num = parseFloat( String( value || '' ).replace( /px$/i, '' ) );
		return Number.isFinite( num ) ? num : fallback;
	},

	async updateHiddenField( includeSnapshots = false ) {
		const el = document.getElementById( 'oc-customisation-data' );
		if ( ! el ) {
			return;
		}
		const layers = {};
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const inp = this.inputs[ layer.id ];
				if ( inp ) {
					this.clampLayerInputValue( layer.id );
					layers[ layer.id ] = {
						type: layer.type,
						...this.inputs[ layer.id ],
					};
				}
			} );
		} );
		const payload = { v: 2, designId: this.data.designId, layers };
		if ( this.selectedDesignVariant ) {
			const variant = this.designVariants.find(
				( item ) => item.id === this.selectedDesignVariant
			);
			payload.designVariant = this.selectedDesignVariant;
			if ( variant?.label ) {
				payload.designVariantLabel = variant.label;
			}
		}
		if ( includeSnapshots ) {
			const snapshots = await this.captureAreaSnapshots();
			if ( Object.keys( snapshots ).length ) {
				payload.snapshots = snapshots;
			}
		}
		if ( this._previewUrl ) {
			payload.previewUrl = this._previewUrl;
		}
		el.value = JSON.stringify( payload );
	},
};

export default cartSerializationMethods;
