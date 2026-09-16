// Upload only through the server sanitizer; never preview the raw local file.
export async function uploadCutLine( file, data ) {
	if (
		! /\.svg$/i.test( file.name ) ||
		! file.size ||
		file.size > 256 * 1024
	) {
		throw new Error( 'Choose an SVG file no larger than 256 KiB.' );
	}
	const body = new FormData();
	body.append( 'action', 'oc_upload_cut_line' );
	body.append( 'nonce', data.nonce || '' );
	body.append( 'file', file );
	const response = await fetch( data.ajaxUrl, { method: 'POST', body } );
	const result = await response.json();
	if (
		! response.ok ||
		! result.success ||
		typeof result.data?.cutLineSvg !== 'string' ||
		! result.data.cutLineSvg
	) {
		throw new Error(
			result.data?.message || 'Unable to upload the cut line SVG.'
		);
	}
	return result.data.cutLineSvg;
}

export function bindCutLineUpload( layer, area, commitChange ) {
	const input = document.getElementById( 'oc-cut-line-file' );
	const status = document.getElementById( 'oc-cut-line-status' );
	input?.addEventListener( 'change', async () => {
		const file = input.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		input.disabled = true;
		status.textContent = 'Uploading SVG…';
		try {
			const svg = await uploadCutLine(
				file,
				window.ocProductsData || {}
			);
			// Switching layers/tabs or undoing while uploading must not mutate stale state.
			if ( ! input.isConnected || ! area?.layers.includes( layer ) ) {
				return;
			}
			layer.settings.cutLineSvg = svg;
			commitChange( { canvas: true, rightColumn: true } );
		} catch ( error ) {
			status.textContent =
				error.message || 'Unable to upload the cut line SVG.';
		} finally {
			input.disabled = false;
			input.value = '';
		}
	} );
}

// The source is sanitized inline SVG supplied by the admin backend.
export function appendCutLinePreview( el, source ) {
	const preview = document.createElement( 'div' );
	preview.className = 'oc-lp oc-lp-cut-line';
	if ( source ) {
		preview.innerHTML = source;
		const svg = preview.querySelector( 'svg' );
		if ( svg ) {
			// Dimension-only SVGs need a viewBox before changing their viewport.
			if ( ! svg.hasAttribute( 'viewBox' ) ) {
				const width =
					svg.width?.baseVal?.value ||
					parseFloat( svg.getAttribute( 'width' ) ) ||
					300;
				const height =
					svg.height?.baseVal?.value ||
					parseFloat( svg.getAttribute( 'height' ) ) ||
					150;
				svg.setAttribute( 'viewBox', `0 0 ${ width } ${ height }` );
			}
			svg.setAttribute( 'preserveAspectRatio', 'none' );
			svg.setAttribute( 'width', '100%' );
			svg.setAttribute( 'height', '100%' );
			svg.style.width = '100%';
			svg.style.height = '100%';
			svg.style.overflow = 'visible';
		}
	} else {
		preview.textContent = 'Upload cut line SVG';
	}
	el.appendChild( preview );
}
