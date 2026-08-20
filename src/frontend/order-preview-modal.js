import './order-preview-modal.scss';

const triggerSelector = '.oc-order-preview-trigger';
let modal;
let previewImage;
let previousFocus;

const closeModal = () => {
	if ( ! modal || modal.hidden ) {
		return;
	}

	modal.hidden = true;
	document.body.classList.remove( 'oc-preview-modal-open' );
	previewImage.removeAttribute( 'src' );
	if ( previousFocus ) {
		previousFocus.focus();
	}
};

const createModal = () => {
	modal = document.createElement( 'div' );
	modal.className = 'oc-preview-modal';
	modal.hidden = true;
	modal.setAttribute( 'role', 'dialog' );
	modal.setAttribute( 'aria-modal', 'true' );
	modal.setAttribute( 'aria-labelledby', 'oc-preview-modal-title' );
	modal.innerHTML = `
		<div class="oc-preview-modal__backdrop" data-oc-preview-close></div>
		<div class="oc-preview-modal__panel" role="document">
			<div class="oc-preview-modal__header">
				<div>
					<p class="oc-preview-modal__eyebrow">Your customisation</p>
					<h2 id="oc-preview-modal-title">Personalised preview</h2>
				</div>
				<button class="oc-preview-modal__close" type="button" data-oc-preview-close aria-label="Close preview">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
				</button>
			</div>
			<div class="oc-preview-modal__image-wrap">
				<div class="oc-preview-modal__loader" aria-hidden="true"></div>
				<img class="oc-preview-modal__image" alt="Personalised product preview" />
			</div>
			<p class="oc-preview-modal__hint">Preview shown for reference. Final colours may vary slightly.</p>
		</div>`;

	document.body.appendChild( modal );
	previewImage = modal.querySelector( '.oc-preview-modal__image' );
	previewImage.addEventListener( 'load', () => {
		modal.classList.add( 'is-loaded' );
	} );
	modal.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '[data-oc-preview-close]' ) ) {
			closeModal();
		}
	} );
};

const openModal = ( trigger ) => {
	if ( ! modal ) {
		createModal();
	}

	previousFocus = trigger;
	modal.classList.remove( 'is-loaded' );
	modal.hidden = false;
	document.body.classList.add( 'oc-preview-modal-open' );
	previewImage.src = trigger.href;
	modal.querySelector( '.oc-preview-modal__close' ).focus();
};

document.addEventListener( 'click', ( event ) => {
	const trigger = event.target.closest( triggerSelector );
	if ( ! trigger ) {
		return;
	}

	event.preventDefault();
	openModal( trigger );
} );

document.addEventListener( 'keydown', ( event ) => {
	if ( 'Escape' === event.key ) {
		closeModal();
	}

	if ( 'Tab' === event.key && modal && ! modal.hidden ) {
		event.preventDefault();
		modal.querySelector( '.oc-preview-modal__close' ).focus();
	}
} );
