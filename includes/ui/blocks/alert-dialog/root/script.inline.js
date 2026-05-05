import { store, getContext, getElement } from '@wordpress/interactivity';

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
			if ( popup ) popup.removeAttribute( 'hidden' );
			if ( backdrop ) backdrop.removeAttribute( 'hidden' );

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
			const popup = root?.querySelector( '[role="alertdialog"]' );
			const backdrop = root?.querySelector( '[aria-hidden]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
			if ( backdrop ) backdrop.setAttribute( 'hidden', '' );

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
