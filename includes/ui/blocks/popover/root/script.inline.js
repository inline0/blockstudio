import { computePosition, flip, shift, offset } from 'https://esm.sh/@floating-ui/dom@1.7.4';
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/popover', {
	state: {
		get ariaExpanded() {
			return getContext().open ? 'true' : 'false';
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = ! ctx.open;

			const root = ref.closest( '[data-bsui-popover-root]' );
			const popup = root?.querySelector( '[role="dialog"]' );

			if ( ctx.open ) {
				if ( popup ) popup.removeAttribute( 'hidden' );
				const trigger = root?.querySelector( '[aria-haspopup="dialog"]' );
				requestAnimationFrame( () => {
					if ( popup && trigger ) {
						computePosition( window.__bsui.getAnchor( trigger ), popup, {
							placement: 'bottom-start',
							middleware: [ offset( 4 ), flip(), shift( { padding: 8 } ) ],
						} ).then( ( { x, y } ) => {
							Object.assign( popup.style, { left: x + 'px', top: y + 'px' } );
						} );
						const focusable = popup.querySelector( window.__bsui.FOCUSABLE );
						( focusable || popup ).focus();
					}
				} );
			} else {
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
		close() {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			ctx.open = false;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-popover-root]' );
			const popup = root?.querySelector( '[role="dialog"]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
			const trigger = root?.querySelector( '[aria-haspopup="dialog"]' );
			window.__bsui.getAnchor( trigger )?.focus();
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				const popup = ref.querySelector( '[role="dialog"]' );
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
		handleEscape( event ) {
			if ( event.key !== 'Escape' ) return;
			const ctx = getContext();
			if ( ! ctx.open ) return;
			ctx.open = false;
			const { ref } = getElement();
			const popup = ref.querySelector( '[role="dialog"]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
			const trigger = ref.querySelector( '[aria-haspopup="dialog"]' );
			window.__bsui.getAnchor( trigger )?.focus();
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
