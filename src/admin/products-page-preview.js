/* eslint-disable no-nested-ternary */

export function createLayerPreviewRenderer( deps ) {
	const { fontLimit, layerLabel, normaliseHex } = deps;

	function clampFontSize( size, settings, scale ) {
		const min = fontLimit( settings?.min_font_size ) * scale;
		const max = fontLimit( settings?.max_font_size ) * scale;
		if ( max && ( ! min || min <= max ) ) {
			size = Math.min( size, max );
		}
		if ( min ) {
			size = Math.max( size, min );
		}
		return size;
	}

	function fitTextPreview( el, fontSize, minFontSize, singleLine = false ) {
		if ( ! el || ! el.clientWidth || ! el.clientHeight ) {
			return;
		}

		el.style.transform = '';
		let size = fontSize;
		const floor = Math.max( 1, minFontSize || 4 );
		while (
			size > floor &&
			( singleLine
				? el.scrollHeight > el.clientHeight
				: el.scrollWidth > el.clientWidth ||
				  el.scrollHeight > el.clientHeight )
		) {
			size = Math.max( floor, size - 1 );
			el.style.fontSize = size + 'px';
		}

		if ( singleLine && el.scrollWidth > el.clientWidth ) {
			el.style.transform =
				'scaleX(' +
				Math.max( 0.01, el.clientWidth / el.scrollWidth ) +
				')';
		}
	}

	function findFont( fontId ) {
		const fonts = ( window.ocProductsData || {} ).fonts || [];
		return (
			fonts.find( ( f ) => Number( f.id ) === Number( fontId ) ) ||
			( ! fontId ? fonts[ 0 ] : null )
		);
	}

	function imageFilterCss( filterId ) {
		filterId = Number( filterId ) || 0;
		if ( ! filterId ) {
			return '';
		}
		const data = window.ocProductsData || {};
		const filter = ( data.imageFilters || [] ).find(
			( item ) => Number( item.id ) === filterId
		);
		if ( ! filter ) {
			return '';
		}
		const value = Number.isFinite( Number( filter.value ) )
			? Number( filter.value )
			: 1;
		switch ( filter.key ) {
			case 'grayscale':
				return 'grayscale(1)';
			case 'sepia':
				return 'sepia(1)';
			case 'brightness':
				return 'brightness(' + Math.max( 0, 1 + value ) + ')';
			case 'contrast':
				return 'contrast(' + Math.max( 0, 1 + value ) + ')';
			case 'saturation':
				return 'saturate(' + Math.max( 0, 1 + value ) + ')';
			case 'hue':
				return 'hue-rotate(' + value * 360 + 'deg)';
			default:
				return '';
		}
	}

	function engravingPreview( material ) {
		if ( material === 'silver_plaque' ) {
			return {
				color: '#17191b',
				filter: 'brightness(0) saturate(100%) opacity(0.92)',
				photoFilter:
					'grayscale(1) contrast(1.35) brightness(0.72) opacity(0.92)',
				shadow: '0 1px 1px rgba(255, 255, 255, 0.18)',
			};
		}

		if ( material === 'leather' ) {
			return {
				color: '#4a2919',
				filter: 'brightness(0) saturate(100%) invert(15%) sepia(31%) saturate(1334%) hue-rotate(343deg) brightness(91%) contrast(91%) opacity(0.86)',
				shadow: '0 1px 1px rgba(225, 174, 121, 0.2)',
			};
		}

		return {
			color: '#dadad6',
			filter: 'brightness(0) saturate(100%) invert(91%) opacity(0.9)',
			shadow: '',
		};
	}

	function applyLayerPreview(
		layer,
		el,
		renderedW,
		renderedH,
		isGhost,
		isEngraving,
		engravingMaterial = 'silver_metal'
	) {
		// Remove any existing preview children
		el.querySelectorAll( '.oc-lp' ).forEach( ( c ) => c.remove() );
		if ( ! layer ) {
			return;
		}

		const s = layer.settings || {};
		const engraving = engravingPreview( engravingMaterial );
		const selectedClipart =
			layer.type === 'clipart'
				? ( window.ocProductsData?.clipartItems || [] ).find(
						( item ) =>
							Number( item.id ) === Number( s.default_clipart_id )
				  )
				: null;
		const clipartUrl = s.default_clipart_url || selectedClipart?.url || '';

		if ( layer.type === 'text' || layer.type === 'textarea' ) {
			const isSingleLine = layer.type === 'text';
			const text =
				s.default_text || layer.label || layerLabel( layer.type );
			const align = s.alignment || 'center';
			const flexAlign =
				align === 'left'
					? 'flex-start'
					: align === 'right'
					? 'flex-end'
					: 'center';
			const lineAlign = [ 'top', 'center', 'bottom' ].includes(
				s.line_alignment
			)
				? s.line_alignment
				: 'top';
			const flexLineAlign =
				lineAlign === 'bottom'
					? 'flex-end'
					: lineAlign === 'center'
					? 'center'
					: 'flex-start';
			const scale = renderedH / Math.max( 1, layer.h );
			const defaultFontSize = fontLimit( s.default_font_size );
			const autoFontSize = Math.max(
				8,
				Math.min(
					renderedH * ( isGhost ? 0.36 : 0.42 ),
					isGhost ? 22 : 30
				)
			);
			const fs = clampFontSize(
				defaultFontSize ? defaultFontSize * scale : autoFontSize,
				s,
				scale
			);
			const font = findFont( s.default_font_id || 0 );

			const d = document.createElement( 'div' );
			d.className = 'oc-lp oc-lp-text';
			d.style.fontSize = fs + 'px';
			d.style.fontWeight = '400';
			d.style.lineHeight = '1.16';
			d.style.whiteSpace = isSingleLine ? 'nowrap' : 'normal';
			d.style.transformOrigin =
				align === 'left'
					? 'left center'
					: align === 'right'
					? 'right center'
					: 'center center';
			d.style.textAlign = align;
			d.style.alignItems = flexAlign;
			d.style.maxWidth = Math.max( 1, renderedW ) + 'px';
			d.style.maxHeight = Math.max( 1, renderedH ) + 'px';
			d.style.color = isEngraving
				? engraving.color
				: normaliseHex( s.default_color );
			if ( isEngraving ) {
				d.style.textShadow = engraving.shadow;
			}
			if ( font ) {
				d.style.fontFamily =
					"'" +
					String( font.name ).replace( /'/g, "\\'" ) +
					"', sans-serif";
			}
			if ( ! isSingleLine ) {
				d.style.justifyContent = flexLineAlign;
			}
			d.textContent = text;
			el.appendChild( d );

			const minFontSize = fontLimit( s.min_font_size )
				? fontLimit( s.min_font_size ) * scale
				: 4;
			fitTextPreview( d, fs, minFontSize, isSingleLine );
			window.requestAnimationFrame( () =>
				fitTextPreview( d, fs, minFontSize, isSingleLine )
			);
			document.fonts?.ready?.then?.( () =>
				fitTextPreview(
					d,
					parseFloat( d.style.fontSize ) || fs,
					minFontSize,
					isSingleLine
				)
			);
		} else if ( layer.type === 'image' || layer.type === 'clipmask' ) {
			if ( layer.type === 'image' && s.default_attachment_url ) {
				const img = document.createElement( 'img' );
				img.className = 'oc-lp oc-lp-media';
				img.src = s.default_attachment_url;
				img.alt = '';
				img.style.filter = isEngraving
					? engraving.photoFilter || engraving.filter
					: imageFilterCss( s.default_image_filter_id );
				if (
					isEngraving &&
					[ 'leather', 'silver_plaque' ].includes( engravingMaterial )
				) {
					img.style.mixBlendMode = 'multiply';
				}
				el.appendChild( img );
				return;
			}

			const fs = Math.max( 14, Math.min( renderedH * 0.35, 40 ) );
			const d = document.createElement( 'div' );
			d.className = 'oc-lp oc-lp-icon';
			d.innerHTML =
				'<svg width="' +
				Math.round( fs ) +
				'" height="' +
				Math.round( fs * 0.8 ) +
				'" viewBox="0 0 24 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
				'<rect x="1" y="4" width="22" height="15" rx="2"/>' +
				'<circle cx="12" cy="11.5" r="3.5"/>' +
				'<path d="M8 4l2-3h4l2 3"/>' +
				'</svg>' +
				'<span>' +
				( layer.type === 'clipmask'
					? 'Upload Clipped Photo'
					: 'Upload Image' ) +
				'</span>';
			if ( layer.type === 'clipmask' ) {
				d.style.borderRadius = '999px';
			}
			el.appendChild( d );
		} else if ( layer.type === 'clipart' && clipartUrl ) {
			const img = document.createElement( 'img' );
			img.className = 'oc-lp oc-lp-media';
			img.src = clipartUrl;
			img.alt = '';
			if (
				isEngraving &&
				( s.default_clipart_recolourable ||
					( selectedClipart?.fileType === 'svg' &&
						selectedClipart.colourChangeable !== false ) )
			) {
				img.style.filter = engraving.filter;
				if ( engravingMaterial === 'leather' ) {
					img.style.mixBlendMode = 'multiply';
				}
			}
			el.appendChild( img );
		} else {
			const icons = {
				mask: '\u25a0',
				spotify: '\u266b',
				lineart: '\u270f',
				clipart: '\u2726',
			};
			const labels = {
				mask: 'Mask',
				spotify: 'Spotify Code',
				lineart: 'Line Art',
				clipart: 'Clipart',
			};
			const fs = Math.max( 14, Math.min( renderedH * 0.35, 36 ) );
			const d = document.createElement( 'div' );
			d.className = 'oc-lp oc-lp-icon';
			if ( isEngraving && layer.type === 'lineart' ) {
				d.style.color = engraving.color;
				d.style.textShadow = engraving.shadow;
			}
			d.innerHTML =
				'<span style="font-size:' +
				Math.round( fs ) +
				'px;">' +
				( icons[ layer.type ] || '' ) +
				'</span><span>' +
				( labels[ layer.type ] || layerLabel( layer.type ) ) +
				'</span>';
			el.appendChild( d );
		}
	}

	return applyLayerPreview;
}
