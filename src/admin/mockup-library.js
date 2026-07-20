/**
 * Admin — Mockup Library JS.
 * Upload via WP media modal + AJAX tag, inline label save, remove.
 * Core interactions are inlined in PHP; this file handles edge cases.
 */

/* global jQuery */

jQuery( function ( $ ) {
	// Flash success on inline label save (form redirects; detect ?saved param).
	const params = new URLSearchParams( window.location.search );
	if ( params.get( 'saved' ) ) {
		const $notice = $(
			'<div class="notice notice-success is-dismissible"><p>Mockup saved.</p></div>'
		);
		$( '.wp-heading-inline' )
			.closest( '.wrap' )
			.find( 'hr.wp-header-end' )
			.after( $notice );
		setTimeout( function () {
			$notice.fadeOut();
		}, 3000 );
	}

	// Prevent accidental navigation when a label field has unsaved changes.
	const dirtyForms = new Set();
	$( document ).on( 'input', 'input[name="oc_mockup_label"]', function () {
		if ( this.form ) {
			dirtyForms.add( this.form );
		}
	} );
	$( document ).on( 'submit', 'form', function () {
		dirtyForms.delete( this );
	} );
	$( window ).on( 'beforeunload', function () {
		if ( dirtyForms.size ) {
			return 'You have unsaved mockup label changes.';
		}
	} );
} );
