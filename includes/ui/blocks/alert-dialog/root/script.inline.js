import { store, getContext, getElement } from '@wordpress/interactivity';

let openEntry = null;

function portalToBody( el ) {
	if ( ! el || el.parentNode === document.body ) return null;
	const placeholder = document.createComment( 'bs-ui-alert-dialog' );
	el.parentNode.insertBefore( placeholder, el );
	document.body.appendChild( el );
	return placeholder;
}

function unportal( el, placeholder ) {
	if ( ! el || ! placeholder?.parentNode ) return;
	placeholder.parentNode.insertBefore( el, placeholder );
	placeholder.remove();
}

function closeAlertDialog( ctx, root ) {
	if ( ! ctx || ! ctx.open ) return;
	ctx.open = false;

	const parts = openEntry;
	const popup = parts?.popup || root?.querySelector( '[data-bsui-alert-dialog-popup]' );
	const backdrop = parts?.backdrop || root?.querySelector( '[data-bsui-alert-dialog-backdrop]' );

	if ( popup ) popup.classList.add( 'bs-ui-entering' );
	if ( backdrop ) backdrop.classList.add( 'bs-ui-entering' );

	setTimeout( () => {
		window.__bsui.unlockScroll();
		if ( popup ) {
			popup.setAttribute( 'hidden', '' );
			popup.classList.remove( 'bs-ui-entering' );
		}
		if ( backdrop ) {
			backdrop.setAttribute( 'hidden', '' );
			backdrop.classList.remove( 'bs-ui-entering' );
		}

		if ( parts ) {
			unportal( popup, parts.popupPlaceholder );
			unportal( backdrop, parts.backdropPlaceholder );
			openEntry = null;
		}

		const trigger = root?.querySelector( '[data-bsui-alert-dialog-trigger]' );
		requestAnimationFrame( () => window.__bsui.getAnchor( trigger )?.focus() );
	}, 150 );
}

// An alert dialog answers Escape like any other modal. It deliberately does
// not answer a backdrop click: the point of the pattern is that the choice
// has to be made rather than dismissed by accident.
document.addEventListener( 'keydown', ( event ) => {
	if ( 'Escape' !== event.key || ! openEntry ) return;
	event.preventDefault();
	closeAlertDialog( openEntry.ctx, openEntry.root );
} );

store( 'bsui/alert-dialog', {
	state: {
		get ariaExpanded() {
			return getContext().open ? 'true' : 'false';
		},
	},
	actions: {
		open() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = true;
			window.__bsui.lockScroll();

			const root = ref.closest( '[data-bsui-alert-dialog-root]' );
			const popup = root?.querySelector( '[data-bsui-alert-dialog-popup]' );
			const backdrop = root?.querySelector( '[data-bsui-alert-dialog-backdrop]' );
			if ( backdrop ) backdrop.removeAttribute( 'hidden' );
			if ( popup ) popup.removeAttribute( 'hidden' );

			openEntry = {
				ctx,
				root,
				popup,
				backdrop,
				backdropPlaceholder: portalToBody( backdrop ),
				popupPlaceholder: portalToBody( popup ),
			};

			if ( popup ) popup.classList.add( 'bs-ui-entering' );
			if ( backdrop ) backdrop.classList.add( 'bs-ui-entering' );
			if ( popup ) void popup.offsetHeight;

			requestAnimationFrame( () => {
				if ( popup ) popup.classList.remove( 'bs-ui-entering' );
				if ( backdrop ) backdrop.classList.remove( 'bs-ui-entering' );
				if ( popup ) {
					const focusable = popup.querySelector( window.__bsui.FOCUSABLE );
					( focusable || popup ).focus();
				}
			} );
		},
		close() {
			const { ref } = getElement();
			closeAlertDialog( getContext(), ref.closest( '[data-bsui-alert-dialog-root]' ) );
		},
		handleFocusTrap( event ) {
			if ( event.key !== 'Tab' ) return;
			const { ref } = getElement();
			const focusable = [ ...ref.querySelectorAll( window.__bsui.FOCUSABLE ) ];
			if ( focusable.length === 0 ) return;
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		},
	},
} );
