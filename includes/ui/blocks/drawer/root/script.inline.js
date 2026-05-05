import { store, getContext, getElement } from '@wordpress/interactivity';

function closeDrawer( ctx, ref ) {
	if ( ! ctx.open ) return;
	const root = ref?.closest( '[data-bsui-drawer-root]' );
	const popup = root?.querySelector( '[role="dialog"]' );
	const backdrop = root?.querySelector( '[aria-hidden]' );

	if ( popup ) popup.classList.add( 'bs-ui-entering' );
	if ( backdrop ) backdrop.classList.add( 'bs-ui-entering' );

	setTimeout( () => {
		ctx.open = false;
		window.__bsui.unlockScroll();
		if ( popup ) popup.classList.remove( 'bs-ui-entering' );
		if ( backdrop ) backdrop.classList.remove( 'bs-ui-entering' );
		if ( popup ) popup.setAttribute( 'hidden', '' );
		if ( backdrop ) backdrop.setAttribute( 'hidden', '' );
		const trigger = root?.querySelector( '[data-bsui-drawer-trigger]' );
		requestAnimationFrame( () => window.__bsui.getAnchor( trigger )?.focus() );
	}, 450 );
}

store( 'bsui/drawer', {
	state: {
		get ariaExpanded() { return getContext().open ? 'true' : 'false'; },
	},
	actions: {
		open() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-drawer-root]' );
			const popup = root?.querySelector( '[role="dialog"]' );
			const backdrop = root?.querySelector( '[aria-hidden]' );

			if ( popup ) popup.removeAttribute( 'hidden' );
			if ( backdrop ) backdrop.removeAttribute( 'hidden' );
			if ( popup ) popup.classList.add( 'bs-ui-entering' );
			if ( backdrop ) backdrop.classList.add( 'bs-ui-entering' );

			ctx.open = true;
			window.__bsui.lockScroll();

			requestAnimationFrame( () => {
				if ( popup ) {
					popup.classList.remove( 'bs-ui-entering' );
					const focusable = popup.querySelector( window.__bsui.FOCUSABLE );
					( focusable || popup ).focus();
				}
				if ( backdrop ) backdrop.classList.remove( 'bs-ui-entering' );
			} );
		},
		close() {
			const ctx = getContext();
			const { ref } = getElement();
			closeDrawer( ctx, ref );
		},
		handleBackdropClick() {
			const ctx = getContext();
			if ( ! ctx.dismissable ) return;
			const { ref } = getElement();
			closeDrawer( ctx, ref );
		},
		handleEscape( event ) {
			if ( event.key !== 'Escape' ) return;
			const ctx = getContext();
			if ( ! ctx.open || ! ctx.dismissable ) return;
			event.preventDefault();
			const { ref } = getElement();
			closeDrawer( ctx, ref );
		},
		handleFocusTrap( event ) {
			if ( event.key !== 'Tab' ) return;
			const { ref } = getElement();
			const focusable = [ ...ref.querySelectorAll( window.__bsui.FOCUSABLE ) ];
			if ( ! focusable.length ) return;
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
