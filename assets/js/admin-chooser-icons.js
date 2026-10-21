/* global ocChooserIcons */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest(
			'[data-oc-icon-select], [data-oc-icon-remove]'
		);
		const control = button?.closest( '[data-oc-chooser-icon]' );
		if ( ! control || control.dataset.busy === '1' ) {
			return;
		}
		const kind = control.dataset.kind;
		const id = control.dataset.id;
		async function save( attachmentId ) {
			control.dataset.busy = '1';
			const buttons = control.querySelectorAll( 'button' );
			buttons.forEach( ( item ) => {
				item.disabled = true;
			} );
			const status = control.querySelector( '[data-oc-icon-status]' );
			status.textContent = 'Saving…';
			try {
				const response = await fetch( ocChooserIcons.url, {
					method: 'POST',
					body: new URLSearchParams( {
						action: 'oc_save_chooser_icon',
						nonce: ocChooserIcons.nonce,
						kind,
						id,
						attachment_id: attachmentId,
					} ),
				} );
				const result = await response.json();
				if ( ! response.ok || ! result.success ) {
					throw new Error(
						result.data?.message || 'Could not save icon.'
					);
				}
				document
					.querySelectorAll( '[data-oc-chooser-icon]' )
					.forEach( ( target ) => {
						if (
							target.dataset.kind !== kind ||
							target.dataset.id !== id
						) {
							return;
						}
						const image = target.querySelector(
							'[data-oc-icon-preview]'
						);
						image.src = result.data.url;
						image.style.display = result.data.url ? '' : 'none';
						target.querySelector(
							'[data-oc-icon-status]'
						).textContent = 'Saved';
					} );
				document.dispatchEvent(
					new CustomEvent( 'oc:chooser-icon-saved', {
						bubbles: true,
						detail: { kind, id, url: result.data.url },
					} )
				);
			} catch ( error ) {
				if ( control.dataset.id === id ) {
					status.textContent =
						error.message || 'Could not save icon.';
				}
			} finally {
				control.dataset.busy = '0';
				buttons.forEach( ( item ) => {
					item.disabled = false;
				} );
			}
		}
		if ( button.hasAttribute( 'data-oc-icon-remove' ) ) {
			save( 0 );
			return;
		}
		const frame = wp.media( {
			title: 'Choose frontend icon',
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			save( frame.state().get( 'selection' ).first().id );
		} );
		frame.open();
	} );
} )();
