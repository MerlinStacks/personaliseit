/* Accessible help disclosures, delegated for dynamically inserted admin content. */
( () => {
	const selector = '[data-oc-help-disclosure]';
	const close = ( help ) => {
		help.querySelector( '.oc-help-toggle' )?.setAttribute(
			'aria-expanded',
			'false'
		);
		const hint = help.querySelector( '.oc-help-hint' );
		if ( hint ) {
			hint.hidden = true;
		}
	};
	const closeAll = () =>
		document.querySelectorAll( selector ).forEach( close );

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest?.(
			`${ selector } > .oc-help-toggle`
		);
		if ( ! button ) {
			if ( ! event.target.closest?.( selector ) ) {
				closeAll();
			}
			return;
		}
		event.preventDefault();
		const help = button.closest( selector );
		const hint = help.querySelector( '.oc-help-hint' );
		const opening = button.getAttribute( 'aria-expanded' ) !== 'true';
		closeAll();
		if ( hint && opening ) {
			hint.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Escape' ) {
			return;
		}
		const help = event.target.closest?.( selector );
		if (
			help
				?.querySelector( '.oc-help-toggle' )
				?.getAttribute( 'aria-expanded' ) === 'true'
		) {
			event.preventDefault();
			close( help );
			help.querySelector( '.oc-help-toggle' ).focus();
		}
	} );

	document.addEventListener( 'focusin', ( event ) => {
		document.querySelectorAll( selector ).forEach( ( help ) => {
			if ( ! help.contains( event.target ) ) {
				close( help );
			}
		} );
	} );
} )();
