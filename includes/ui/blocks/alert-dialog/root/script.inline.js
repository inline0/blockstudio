import { store, getContext, getElement } from '@wordpress/interactivity';

const openParts = new WeakMap();

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
			const popup = root?.querySelector( '[role="alertdialog"]' );
			const backdrop = root?.querySelector( '[aria-hidden]' );
			if ( backdrop ) backdrop.removeAttribute( 'hidden' );
			if ( popup ) popup.removeAttribute( 'hidden' );

			if ( root ) {
				openParts.set( root, {
					popup,
					backdrop,
					backdropPlaceholder: portalToBody( backdrop ),
					popupPlaceholder: portalToBody( popup ),
				} );
			}

			requestAnimationFrame( () => {
				if ( popup ) {
					const focusable = popup.querySelector( window.__bsui.FOCUSABLE );
					( focusable || popup ).focus();
				}
			} );
		},
		close() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = false;
			window.__bsui.unlockScroll();

			const root = ref.closest( '[data-bsui-alert-dialog-root]' );
			const parts = root ? openParts.get( root ) : null;
			const popup = parts?.popup || root?.querySelector( '[role="alertdialog"]' );
			const backdrop = parts?.backdrop || root?.querySelector( '[aria-hidden]' );

			if ( popup ) popup.setAttribute( 'hidden', '' );
			if ( backdrop ) backdrop.setAttribute( 'hidden', '' );

			if ( parts ) {
				unportal( popup, parts.popupPlaceholder );
				unportal( backdrop, parts.backdropPlaceholder );
				openParts.delete( root );
			}

			requestAnimationFrame( () => {
				const trigger = root?.querySelector( '[data-bsui-alert-dialog-trigger]' );
				window.__bsui.getAnchor( trigger )?.focus();
			} );
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
