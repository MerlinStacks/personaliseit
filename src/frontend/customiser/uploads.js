/**
 * Uppy upload zone setup and upload UI helpers.
 */

/* eslint-disable no-console */

import { rasterDimensionsForLayer } from '../../shared/render-math';

const SERVER_UPLOAD_FORMATS = [
	'jpg',
	'jpeg',
	'png',
	'svg',
	'pdf',
	'eps',
	'webp',
];

const uploadMethods = {
	beginArtworkOperation( type, layerId = 0 ) {
		const operation = { type, layerId, settled: false };
		this._artworkOperations.add( operation );
		this.artworkPendingCount += 1;
		return operation;
	},

	finishArtworkOperation( operation ) {
		if ( ! operation || operation.settled ) {
			return;
		}
		operation.settled = true;
		this._artworkOperations.delete( operation );
		this.artworkPendingCount = Math.max( 0, this.artworkPendingCount - 1 );
	},

	cancelArtworkOperations() {
		Array.from( this._artworkOperations ).forEach( ( operation ) =>
			this.finishArtworkOperation( operation )
		);
	},

	async getTrackedImageMeta( url, layerId = 0 ) {
		// The handle is deliberately consumed in finally across every return path.
		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		const operation = this.beginArtworkOperation( 'metadata', layerId );
		try {
			return await this.getImageMeta( url );
		} finally {
			this.finishArtworkOperation( operation );
		}
	},

	isVectorArtwork( input ) {
		const type = String(
			input?.sourceArtworkFileType || input?.artworkFileType || ''
		)
			.toLowerCase()
			.replace( /^\./, '' );
		if ( [ 'svg', 'pdf', 'eps' ].includes( type ) ) {
			return true;
		}
		const originalUrl = String(
			input?.sourceOriginalAttachmentUrl ||
				input?.originalAttachmentUrl ||
				input?.sourceAttachmentUrl ||
				input?.attachmentUrl ||
				''
		);
		return /\.(?:svg|pdf|eps)(?:[?#]|$)/i.test( originalUrl );
	},

	resolutionForLayer( layerId ) {
		const layer = this.getLayerById( layerId );
		const area = this.areas[ this.areaIndexForLayer( layerId ) ];
		return rasterDimensionsForLayer( layer, this.areaBounds( area ) );
	},

	cancelAiFilterForLayer( layerId ) {
		this.aiFilterGenerations[ layerId ] =
			( this.aiFilterGenerations[ layerId ] || 0 ) + 1;
		this.aiFilterAbortControllers[ layerId ]?.abort();
		delete this.aiFilterAbortControllers[ layerId ];
	},

	async applyInitialAiFilters() {
		if ( this._variationSwitchPending ) {
			return;
		}
		const designGeneration = this._designGeneration;
		const started = new Set();
		for ( const [ layerId, input ] of Object.entries( this.inputs ) ) {
			const canonicalId = this.canonicalLinkedLayerId(
				Number( layerId )
			);
			if (
				canonicalId !== Number( layerId ) ||
				started.has( canonicalId ) ||
				this.aiFilterAbortControllers[ canonicalId ]
			) {
				continue;
			}
			started.add( canonicalId );
			const filterId = Number( input?.imageFilterId || 0 );
			const sourceId = Number( input?.sourceAttachmentId || 0 );
			const attachmentId = Number( input?.attachmentId || 0 );
			const filter = ( this.data?.imageFilters || [] ).find(
				( item ) => Number( item.id ) === filterId
			);
			if ( filter?.isAi && sourceId && attachmentId === sourceId ) {
				await this.applyAiImageFilter( canonicalId, filterId );
				if ( designGeneration !== this._designGeneration ) {
					return;
				}
			}
		}
	},

	async setupUploadZones() {
		const designGeneration = this._designGeneration;
		const zoneEls = Array.from(
			document.querySelectorAll( '[data-oc-upload-zone]' )
		);
		if ( ! zoneEls.length ) {
			return;
		}

		let modules;
		try {
			modules = await Promise.all( [
				import( '@uppy/core' ),
				import( '@uppy/drag-drop' ),
				import( '@uppy/xhr-upload' ),
			] );
		} catch ( error ) {
			if ( designGeneration === this._designGeneration ) {
				zoneEls.forEach( ( zoneEl ) =>
					this.showUploadImportFailure( zoneEl, error )
				);
			}
			return;
		}
		const [
			{ default: Uppy },
			{ default: DragDrop },
			{ default: XHRUpload },
		] = modules;
		if ( designGeneration !== this._designGeneration ) {
			return;
		}

		zoneEls.forEach( ( zoneEl ) => {
			if ( ! zoneEl.isConnected || zoneEl.dataset.ocUppyReady === '1' ) {
				return;
			}
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
			const normalisedGlobalFormats = globalFormats
				.map( ( f ) => String( f ).toLowerCase().replace( /^\./, '' ) )
				.filter( ( ext ) => SERVER_UPLOAD_FORMATS.includes( ext ) );
			const effective = layerFormats.length
				? layerFormats
						.map( ( f ) =>
							String( f ).toLowerCase().replace( /^\./, '' )
						)
						.filter( ( ext ) =>
							normalisedGlobalFormats.includes( ext )
						)
				: normalisedGlobalFormats;
			const allowedExt = [ ...new Set( effective ) ].map(
				( ext ) => `.${ ext }`
			);
			if ( ! allowedExt.length ) {
				this.showUploadError(
					zoneEl,
					'This layer does not allow artwork file formats.'
				);
				return;
			}

			const layerMaxMb = parseInt( layer?.settings?.max_size_mb, 10 );
			const globalMaxMb = parseInt( this.data.maxUploadSizeMb, 10 );
			let maxMb = globalMaxMb > 0 ? globalMaxMb : 10;
			if ( layerMaxMb > 0 ) {
				maxMb = Math.min( maxMb, layerMaxMb );
			}

			let activeGeneration = this.uploadGenerations[ lid ] || 0;
			const fileGenerations = new Map();
			const fileOperations = new Map();
			const finishFileTransfer = ( fileId ) => {
				const operation = fileOperations.get( fileId );
				this.finishArtworkOperation( operation );
				fileOperations.delete( fileId );
			};
			const uppy = new Uppy( {
				autoProceed: true,
				onBeforeFileAdded: () => {
					activeGeneration += 1;
					this.uploadGenerations[ lid ] = activeGeneration;
					this.cancelAiFilterForLayer( lid );
					uppy.getFiles().forEach( ( existingFile ) =>
						uppy.removeFile( existingFile.id )
					);
					return true;
				},
				restrictions: {
					maxNumberOfFiles: 1,
					maxFileSize: maxMb * 1024 * 1024,
					allowedFileTypes: allowedExt,
				},
			} );
			uppy.on( 'file-added', ( file ) => {
				fileGenerations.set( file.id, activeGeneration );
				fileOperations.set(
					file.id,
					this.beginArtworkOperation( 'upload', lid )
				);
				this.setUploadProgress( zoneEl, 0, 'Starting upload...' );
				this.showUploadError( zoneEl, '' );
			} );
			uppy.on( 'file-removed', ( file ) =>
				finishFileTransfer( file?.id )
			);
			uppy.on( 'cancel-all', () => {
				Array.from( fileOperations.keys() ).forEach(
					finishFileTransfer
				);
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
				headers: this.restHeaders(),
			} );
			zoneEl.dataset.ocUppyReady = '1';
			this.uppyInstances.add( uppy );
			this.applyUploadZoneAccessibility( zoneEl, layer );
			this.requestStateAnimationFrame( () => {
				if ( zoneEl.isConnected ) {
					this.applyUploadZoneAccessibility( zoneEl, layer );
				}
			} );
			if ( this.isProductionImageInput( this.inputs[ lid ] ) ) {
				this.setUploadZoneState( zoneEl, 'uploaded' );
			}

			uppy.on( 'upload-progress', ( file, progress ) => {
				if (
					fileGenerations.get( file?.id ) !==
					this.uploadGenerations[ lid ]
				) {
					return;
				}
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
				const generation = fileGenerations.get( file?.id );
				finishFileTransfer( file?.id );
				if ( generation !== this.uploadGenerations[ lid ] ) {
					return;
				}
				this.setUploadProgress( zoneEl, 100, '' );
				if ( ! res?.body ) {
					this.setUploadZoneState(
						zoneEl,
						this.isProductionImageInput( this.inputs[ lid ] )
							? 'uploaded-error'
							: 'error'
					);
					this.showUploadError(
						zoneEl,
						'Upload succeeded but server returned no data.'
					);
					return;
				}
				const attachmentId = Number( res.body.attachment_id || 0 );
				const attachmentUrl = String( res.body.preview_url || '' );
				if ( ! attachmentId || ! attachmentUrl ) {
					this.setUploadZoneState(
						zoneEl,
						this.isProductionImageInput( this.inputs[ lid ] )
							? 'uploaded-error'
							: 'error'
					);
					this.showUploadError(
						zoneEl,
						'Server did not return usable artwork data.'
					);
					return;
				}

				const artworkFileType = String(
					res.body.file_type || file?.extension || ''
				).toLowerCase();
				const candidate = {
					...( this.inputs[ lid ] || {} ),
					attachmentId,
					attachmentUrl,
					sourceAttachmentId: attachmentId,
					sourceAttachmentUrl: attachmentUrl,
					originalAttachmentUrl: String(
						res.body.original_url || attachmentUrl
					),
					sourceOriginalAttachmentUrl: String(
						res.body.original_url || attachmentUrl
					),
					artworkFileType,
					sourceArtworkFileType: artworkFileType,
					previewAttachmentId: Number(
						res.body.preview_attachment_id || 0
					),
					sourcePreviewAttachmentId: Number(
						res.body.preview_attachment_id || 0
					),
					imageMeta: null,
					sourceImageMeta: null,
				};
				const meta = await this.getTrackedImageMeta(
					attachmentUrl,
					lid
				);
				if ( generation !== this.uploadGenerations[ lid ] ) {
					return;
				}
				candidate.imageMeta = meta;
				candidate.sourceImageMeta = meta;
				const threshold = this.resolutionForLayer( lid );
				const isVector = this.isVectorArtwork( candidate );
				const belowThreshold =
					! isVector &&
					meta &&
					( meta.width < threshold.width ||
						meta.height < threshold.height );
				const belowHalf =
					belowThreshold &&
					( meta.width < threshold.width * 0.5 ||
						meta.height < threshold.height * 0.5 );
				const warnEl = document.querySelector(
					`.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]`
				);

				if ( belowHalf ) {
					if ( warnEl ) {
						warnEl.className = 'oc-resolution-warning oc-res-error';
						warnEl.textContent = `This image is too low resolution for quality printing. Minimum required: ${ threshold.width } x ${ threshold.height } pixels.`;
						warnEl.style.display = '';
					}
					this.setUploadZoneState(
						zoneEl,
						this.isProductionImageInput( this.inputs[ lid ] )
							? 'uploaded-error'
							: 'error'
					);
					this.showUploadError(
						zoneEl,
						'Image resolution too low. Please upload a higher resolution image.'
					);
					return;
				}

				if ( warnEl ) {
					if ( belowThreshold ) {
						warnEl.className =
							'oc-resolution-warning oc-res-warning';
						warnEl.textContent = `This image may not print clearly at full size. Recommended minimum: ${ threshold.width } x ${ threshold.height } pixels.`;
						warnEl.style.display = '';
					} else {
						warnEl.style.display = 'none';
					}
				}

				this.inputs[ lid ] = candidate;
				this.syncLinkedImageInput( lid );
				const filterApplied = await this.applyAiImageFilter(
					lid,
					candidate.imageFilterId || 0,
					zoneEl
				);
				if ( generation !== this.uploadGenerations[ lid ] ) {
					return;
				}
				this.setUploadZoneState(
					zoneEl,
					filterApplied ? 'uploaded' : 'uploaded-error'
				);
				this.syncLinkedImageInput( lid );
				this.requestPreviewFocus();
				this.scheduleRedraw( this.areaIndexForLayer( lid ) );
				this.updateHiddenField();
				if ( filterApplied ) {
					this.showUploadError( zoneEl, '' );
				}
			} );

			uppy.on( 'upload-error', ( file, error, response ) => {
				finishFileTransfer( file?.id );
				if (
					fileGenerations.get( file?.id ) !==
					this.uploadGenerations[ lid ]
				) {
					return;
				}
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
				this.setUploadZoneState(
					zoneEl,
					this.isProductionImageInput( this.inputs[ lid ] )
						? 'uploaded-error'
						: 'error'
				);
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError( zoneEl, msg );
			} );
			uppy.on( 'restriction-failed', ( file, error ) => {
				finishFileTransfer( file?.id );
				this.setUploadZoneState(
					zoneEl,
					this.isProductionImageInput( this.inputs[ lid ] )
						? 'uploaded-error'
						: 'error'
				);
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError(
					zoneEl,
					error?.message || 'File not allowed.'
				);
			} );
		} );
	},

	async applyAiImageFilter( layerId, filterId, zoneEl = null ) {
		const input = this.inputs[ layerId ];
		if ( ! input ) {
			return false;
		}
		filterId = Number( filterId || 0 );
		this.cancelAiFilterForLayer( layerId );
		const generation = this.aiFilterGenerations[ layerId ];
		const designGeneration = this._designGeneration;
		input.imageFilterId = filterId;
		const sourceId = Number(
			input.sourceAttachmentId || input.attachmentId || 0
		);
		const sourceUrl =
			input.sourceAttachmentUrl || input.attachmentUrl || '';
		const isCurrent = () =>
			generation === this.aiFilterGenerations[ layerId ] &&
			designGeneration === this._designGeneration &&
			this.inputs[ layerId ] === input &&
			Number( input.imageFilterId || 0 ) === filterId &&
			this._customisationActive;
		if ( ! filterId ) {
			this.restoreSourceArtwork( input, sourceId, sourceUrl );
			delete this.aiFilterErrors[ layerId ];
			this.syncLinkedImageInput( layerId );
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return true;
		}
		const filter = ( this.data?.imageFilters || [] ).find(
			( item ) => Number( item.id ) === Number( filterId )
		);
		if ( ! filter?.isAi ) {
			this.restoreSourceArtwork( input, sourceId, sourceUrl );
			delete this.aiFilterErrors[ layerId ];
			this.syncLinkedImageInput( layerId );
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return true;
		}
		if ( ! sourceId || ! sourceUrl || ! this.data?.applyImageFilterUrl ) {
			this.aiFilterErrors[ layerId ] =
				'Upload an image before applying this filter.';
			this.syncLinkedImageInput( layerId );
			this.updateHiddenField();
			return false;
		}

		const request = this.createStateAbortController( 30000 );
		const controller = request.controller;
		this.aiFilterAbortControllers[ layerId ] = controller;
		// The handle is deliberately consumed in finally across every return path.
		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		const operation = this.beginArtworkOperation( 'filter', layerId );
		delete this.aiFilterErrors[ layerId ];
		const targetZone =
			zoneEl ||
			document.querySelector( `[data-oc-upload-zone="${ layerId }"]` );
		if ( targetZone ) {
			this.setUploadProgress( targetZone, 100, 'Creating AI preview...' );
			this.showUploadError( targetZone, '' );
		}

		const variationId = this.currentVariationId();
		try {
			const response = await fetch( this.data.applyImageFilterUrl, {
				method: 'POST',
				signal: controller.signal,
				headers: this.restHeaders( {
					'Content-Type': 'application/json',
				} ),
				body: JSON.stringify( {
					source_attachment_id: sourceId,
					filter_id: Number( filterId ),
					layer_id: Number( layerId ),
					design_id: Number( this.data.designId || 0 ),
					product_id: Number( this.data.productId || 0 ),
					variation_id: variationId,
				} ),
			} );
			const json = await response.json().catch( () => null );
			if (
				! response.ok ||
				! json?.attachment_id ||
				! json?.preview_url
			) {
				throw new Error(
					json?.message || 'The AI filter could not be applied.'
				);
			}
			if ( ! isCurrent() ) {
				return false;
			}
			const filteredAttachmentId = Number( json.attachment_id );
			const filteredAttachmentUrl = String( json.preview_url );
			const filteredArtworkFileType = String(
				json.file_type || input.artworkFileType || ''
			).toLowerCase();
			const imageMeta = await this.getTrackedImageMeta(
				filteredAttachmentUrl,
				layerId
			);
			if ( ! isCurrent() ) {
				return false;
			}
			input.attachmentId = filteredAttachmentId;
			input.attachmentUrl = filteredAttachmentUrl;
			input.imageFilterId = Number( filterId );
			input.artworkFileType = filteredArtworkFileType;
			input.originalAttachmentUrl = String(
				json.original_url || filteredAttachmentUrl
			);
			input.previewAttachmentId = Number(
				json.preview_attachment_id || 0
			);
			input.imageMeta = imageMeta;
			delete this.aiFilterErrors[ layerId ];
			this.syncLinkedImageInput( layerId );
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return true;
		} catch ( error ) {
			if ( ! isCurrent() ) {
				return false;
			}
			const message = request.timedOut()
				? 'The AI filter timed out. Please try again.'
				: error?.message || 'The AI filter could not be applied.';
			this.aiFilterErrors[ layerId ] = message;
			this.restoreSourceArtwork( input, sourceId, sourceUrl );
			this.syncLinkedImageInput( layerId );
			if ( targetZone ) {
				this.showUploadError( targetZone, message );
			}
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return false;
		} finally {
			this.finishArtworkOperation( operation );
			request.release();
			if ( this.aiFilterAbortControllers[ layerId ] === controller ) {
				delete this.aiFilterAbortControllers[ layerId ];
				if ( targetZone ) {
					this.setUploadProgress( targetZone, 0, '' );
				}
			}
		}
	},

	restoreSourceArtwork( input, sourceId, sourceUrl ) {
		if ( ! sourceId || ! sourceUrl ) {
			return;
		}
		input.attachmentId = sourceId;
		input.attachmentUrl = sourceUrl;
		input.originalAttachmentUrl =
			input.sourceOriginalAttachmentUrl || sourceUrl;
		input.artworkFileType =
			input.sourceArtworkFileType || input.artworkFileType || '';
		input.previewAttachmentId = Number(
			input.sourcePreviewAttachmentId || 0
		);
		input.imageMeta = input.sourceImageMeta || input.imageMeta || null;
	},

	setUploadZoneState( zoneEl, state ) {
		zoneEl.classList.toggle(
			'oc-upload-zone--uploaded',
			state === 'uploaded' || state === 'uploaded-error'
		);
		zoneEl.classList.toggle(
			'oc-upload-zone--error',
			state === 'error' || state === 'uploaded-error'
		);

		const browse = zoneEl.querySelector( '.uppy-DragDrop-browse' );
		const note = zoneEl.querySelector( '.uppy-DragDrop-note' );
		if ( browse ) {
			browse.textContent =
				state === 'uploaded' || state === 'uploaded-error'
					? 'Image uploaded'
					: 'Tap / click here to upload your image';
		}
		if ( note ) {
			if ( ! note.dataset.ocOriginalText ) {
				note.dataset.ocOriginalText = note.textContent;
			}
			note.textContent =
				state === 'uploaded' || state === 'uploaded-error'
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
			labelEl.setAttribute( 'aria-live', 'polite' );
		}
		if ( track ) {
			track.setAttribute( 'aria-valuenow', String( safePercent ) );
			track.setAttribute( 'aria-label', label || 'Upload progress' );
		}
		if ( bar ) {
			bar.style.width = `${ safePercent }%`;
		}
		progressEl.style.display = label ? '' : 'none';
	},

	showUploadImportFailure( zoneEl, error ) {
		console.warn( '[OC] Upload controls failed to load:', error );
		this.setUploadZoneState( zoneEl, 'error' );
		this.showUploadError(
			zoneEl,
			'Upload controls could not load. Check your connection and retry.'
		);
		const errorEl = zoneEl
			.closest( '.oc-artwork-wrap' )
			?.querySelector( '.oc-artwork-error' );
		if ( ! errorEl || errorEl.querySelector( '[data-oc-upload-retry]' ) ) {
			return;
		}
		const retry = document.createElement( 'button' );
		retry.type = 'button';
		retry.className = 'oc-upload-retry';
		retry.dataset.ocUploadRetry = '1';
		retry.textContent = 'Retry upload controls';
		retry.addEventListener(
			'click',
			() => {
				retry.disabled = true;
				zoneEl.removeAttribute( 'data-oc-uppy-ready' );
				this._uploadSetupPromise = this.setupUploadZones();
			},
			{ signal: this._panelListenerController?.signal }
		);
		errorEl.append( document.createTextNode( ' ' ), retry );
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
			err.id = `oc-artwork-error-${
				zoneEl.dataset.ocUploadZone || 'upload'
			}`;
			err.setAttribute( 'role', 'status' );
			err.setAttribute( 'aria-live', 'polite' );
			err.setAttribute( 'aria-atomic', 'true' );
			wrap.appendChild( err );
		}
		err.replaceChildren( document.createTextNode( message || '' ) );
		err.style.display = message ? '' : 'none';
		if ( message ) {
			const describedBy = new Set(
				String( zoneEl.getAttribute( 'aria-describedby' ) || '' )
					.split( /\s+/ )
					.filter( Boolean )
			);
			describedBy.add( err.id );
			zoneEl.setAttribute(
				'aria-describedby',
				Array.from( describedBy ).join( ' ' )
			);
		}
	},
};

export default uploadMethods;
