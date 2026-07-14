/**
 * Uppy upload zone setup and upload UI helpers.
 */

/* eslint-disable no-console */

const SERVER_UPLOAD_FORMATS = [
	'jpg',
	'jpeg',
	'png',
	'svg',
	'pdf',
	'eps',
	'webp',
];

const DEFAULT_UPLOAD_EXTENSIONS = [
	'.jpg',
	'.jpeg',
	'.png',
	'.svg',
	'.pdf',
	'.webp',
];

const uploadMethods = {
	async setupUploadZones() {
		const zoneEls = Array.from(
			document.querySelectorAll( '[data-oc-upload-zone]' )
		);
		if ( ! zoneEls.length ) {
			return;
		}

		const [
			{ default: Uppy },
			{ default: DragDrop },
			{ default: XHRUpload },
		] = await Promise.all( [
			import( '@uppy/core' ),
			import( '@uppy/drag-drop' ),
			import( '@uppy/xhr-upload' ),
		] );

		zoneEls.forEach( ( zoneEl ) => {
			const lid = parseInt( zoneEl.dataset.ocUploadZone, 10 );
			if ( ! lid ) {
				return;
			}
			const uploadUrl = this.data?.uploadUrl || '';
			if ( ! uploadUrl ) {
				this.showUploadError(
					zoneEl,
					'Uploads are unavailable right now.'
				);
				return;
			}

			// Find the layer's per-layer settings; fall back to global defaults.
			let layer = null;
			for ( const area of this.areas ) {
				layer = ( area.layers || [] ).find( ( l ) => l.id === lid );
				if ( layer ) {
					break;
				}
			}
			if ( ! layer ) {
				console.warn( '[OC] Upload zone has no matching layer:', lid );
				return;
			}
			const layerFormats = Array.isArray( layer?.settings?.formats )
				? layer.settings.formats
				: [];
			const globalFormats = Array.isArray( this.data.allowedFormats )
				? this.data.allowedFormats
				: [];
			const effective = (
				layerFormats.length ? layerFormats : globalFormats
			)
				.map( ( f ) => String( f ).toLowerCase().replace( /^\./, '' ) )
				.filter( ( ext ) => SERVER_UPLOAD_FORMATS.includes( ext ) );
			const allowedExt = effective.length
				? effective.map( ( ext ) => `.${ ext }` )
				: DEFAULT_UPLOAD_EXTENSIONS;

			const layerMaxMb = parseInt( layer?.settings?.max_size_mb, 10 );
			const globalMaxMb = parseInt( this.data.maxUploadSizeMb, 10 );
			let maxMb = 10;
			if ( layerMaxMb > 0 ) {
				maxMb = layerMaxMb;
			} else if ( globalMaxMb > 0 ) {
				maxMb = globalMaxMb;
			}

			const uppy = new Uppy( {
				autoProceed: true,
				onBeforeFileAdded: () => {
					uppy.getFiles().forEach( ( existingFile ) =>
						uppy.removeFile( existingFile.id )
					);
					this.setUploadZoneState( zoneEl, '' );
					const warnEl = document.querySelector(
						`.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]`
					);
					if ( warnEl ) {
						warnEl.style.display = 'none';
					}
					this.setUploadProgress( zoneEl, 0, 'Starting upload...' );
					this.showUploadError( zoneEl, '' );
					return true;
				},
				restrictions: {
					maxNumberOfFiles: 1,
					maxFileSize: maxMb * 1024 * 1024,
					allowedFileTypes: allowedExt,
				},
			} );
			uppy.use( DragDrop, {
				target: zoneEl,
				note:
					'We accept ' +
					( allowedExt.length
						? allowedExt
								.map( ( e ) =>
									e.replace( '.', '' ).toUpperCase()
								)
								.join( ', ' )
						: 'JPG, PNG, PDF, EPS' ) +
					' and other common image types.',
				locale: {
					strings: {
						dropHereOr: '%{browse}',
						browse: 'Tap / click here to upload your image',
					},
				},
			} );
			uppy.use( XHRUpload, {
				endpoint: this.uploadEndpoint( uploadUrl, lid ),
				formData: true,
				fieldName: 'artwork',
			} );

			uppy.on( 'upload-progress', ( file, progress ) => {
				const percent = progress?.bytesTotal
					? Math.round(
							( progress.bytesUploaded / progress.bytesTotal ) *
								100
					  )
					: 0;
				this.setUploadProgress(
					zoneEl,
					percent,
					`Uploading ${ percent }%`
				);
			} );

			uppy.on( 'upload-success', async ( file, res ) => {
				this.setUploadProgress( zoneEl, 100, '' );
				if ( ! res?.body ) {
					this.setUploadZoneState( zoneEl, 'error' );
					this.showUploadError(
						zoneEl,
						'Upload succeeded but server returned no data.'
					);
					return;
				}
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].attachmentId = res.body.attachment_id || 0;
				this.inputs[ lid ].attachmentUrl = res.body.preview_url || '';
				this.inputs[ lid ].imageMeta = null;
				if ( ! this.inputs[ lid ].attachmentUrl ) {
					this.setUploadZoneState( zoneEl, 'error' );
					this.showUploadError(
						zoneEl,
						'Server did not return a preview URL.'
					);
					return;
				}
				const meta = await this.getImageMeta(
					this.inputs[ lid ].attachmentUrl
				);
				if ( meta && this.inputs[ lid ] ) {
					this.inputs[ lid ].imageMeta = meta;
					const thresholdW = Math.round( layer.w * ( 300 / 72 ) );
					const thresholdH = Math.round( layer.h * ( 300 / 72 ) );
					const warnEl = document.querySelector(
						`.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]`
					);
					if ( warnEl ) {
						const belowThreshold =
							meta.width < thresholdW || meta.height < thresholdH;
						const belowHalf =
							meta.width < thresholdW * 0.5 ||
							meta.height < thresholdH * 0.5;
						if ( belowHalf ) {
							warnEl.className =
								'oc-resolution-warning oc-res-error';
							warnEl.textContent = `This image is too low resolution for quality printing. Minimum required: ${ thresholdW } x ${ thresholdH } pixels.`;
							warnEl.style.display = '';
							this.inputs[ lid ].attachmentId = 0;
							this.inputs[ lid ].attachmentUrl = '';
							this.inputs[ lid ].imageMeta = null;
							this.syncLinkedLayerInput( lid, [
								'attachmentId',
								'attachmentUrl',
								'imageMeta',
							] );
							this.setUploadZoneState( zoneEl, 'error' );
							this.showUploadError(
								zoneEl,
								'Image resolution too low. Please upload a higher resolution image.'
							);
							this.scheduleRedraw(
								this.areaIndexForLayer( lid )
							);
							this.updateHiddenField();
							return;
						} else if ( belowThreshold ) {
							warnEl.className =
								'oc-resolution-warning oc-res-warning';
							warnEl.textContent = `This image may not print clearly at full size. Recommended minimum: ${ thresholdW } x ${ thresholdH } pixels.`;
							warnEl.style.display = '';
						} else {
							warnEl.style.display = 'none';
						}
					}
				}
				this.setUploadZoneState( zoneEl, 'uploaded' );
				this.syncLinkedLayerInput( lid, [
					'attachmentId',
					'attachmentUrl',
					'imageMeta',
				] );
				this.requestPreviewFocus();
				this.scheduleRedraw( this.areaIndexForLayer( lid ) );
				this.updateHiddenField();
				this.showUploadError( zoneEl, '' );
			} );

			uppy.on( 'upload-error', ( file, error, response ) => {
				let responseBody = response?.body || null;
				if ( ! responseBody && response?.responseText ) {
					try {
						responseBody = JSON.parse( response.responseText );
					} catch {
						responseBody = { message: response.responseText };
					}
				}
				const msg =
					responseBody?.message || error?.message || 'Upload failed.';
				console.warn( '[OC] Upload error:', msg, response );
				this.setUploadZoneState( zoneEl, 'error' );
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError( zoneEl, msg );
			} );
			uppy.on( 'restriction-failed', ( file, error ) => {
				this.setUploadZoneState( zoneEl, 'error' );
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError(
					zoneEl,
					error?.message || 'File not allowed.'
				);
			} );
		} );
	},

	setUploadZoneState( zoneEl, state ) {
		zoneEl.classList.toggle(
			'oc-upload-zone--uploaded',
			state === 'uploaded'
		);
		zoneEl.classList.toggle( 'oc-upload-zone--error', state === 'error' );

		const browse = zoneEl.querySelector( '.uppy-DragDrop-browse' );
		const note = zoneEl.querySelector( '.uppy-DragDrop-note' );
		if ( browse ) {
			browse.textContent =
				state === 'uploaded'
					? 'Image uploaded'
					: 'Tap / click here to upload your image';
		}
		if ( note ) {
			if ( ! note.dataset.ocOriginalText ) {
				note.dataset.ocOriginalText = note.textContent;
			}
			note.textContent =
				state === 'uploaded'
					? 'Click to replace image'
					: note.dataset.ocOriginalText || note.textContent;
		}
	},

	setUploadProgress( zoneEl, percent, label ) {
		const wrap = zoneEl.closest( '.oc-artwork-wrap' );
		if ( ! wrap ) {
			return;
		}
		let progressEl = wrap.querySelector( '.oc-upload-progress' );
		if ( ! progressEl ) {
			progressEl = document.createElement( 'div' );
			progressEl.className = 'oc-upload-progress';
			progressEl.innerHTML =
				'<div class="oc-upload-progress-label"></div><div class="oc-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="oc-upload-progress-bar"></div></div>';
			zoneEl.insertAdjacentElement( 'afterend', progressEl );
		}

		const safePercent = Math.max(
			0,
			Math.min( 100, parseInt( percent, 10 ) || 0 )
		);
		const labelEl = progressEl.querySelector( '.oc-upload-progress-label' );
		const track = progressEl.querySelector( '.oc-upload-progress-track' );
		const bar = progressEl.querySelector( '.oc-upload-progress-bar' );

		if ( labelEl ) {
			labelEl.textContent = label || '';
		}
		if ( track ) {
			track.setAttribute( 'aria-valuenow', String( safePercent ) );
		}
		if ( bar ) {
			bar.style.width = `${ safePercent }%`;
		}
		progressEl.style.display = label ? '' : 'none';
	},

	showUploadError( zoneEl, message ) {
		const wrap = zoneEl.closest( '.oc-artwork-wrap' );
		if ( ! wrap ) {
			return;
		}
		let err = wrap.querySelector( '.oc-artwork-error' );
		if ( ! err ) {
			err = document.createElement( 'div' );
			err.className = 'oc-artwork-error';
			err.style.cssText = 'color:#b32d2e;font-size:12px;margin-top:6px;';
			wrap.appendChild( err );
		}
		err.textContent = message || '';
		err.style.display = message ? '' : 'none';
	},
};

export default uploadMethods;
