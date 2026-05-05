import { computePosition, flip, shift, offset } from 'https://esm.sh/@floating-ui/dom@1.7.4';
import { store, getContext, getElement } from '@wordpress/interactivity';

const ITEM_SELECTOR = '[role="menuitem"]:not([aria-disabled="true"])';

store( 'bsui/context-menu', {
	state: {
		get popupStyle() {
			const { x, y } = getContext();
			return `position:fixed;left:${x}px;top:${y}px`;
		},
	},
	actions: {
		handleContextMenu( event ) {
			event.preventDefault();
			const ctx = getContext();
			ctx.open = true;
			ctx.activeIndex = -1;

			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-context-menu-root]' );
			const menuPopup = root?.querySelector( '[role="menu"]' );
			if ( menuPopup ) menuPopup.removeAttribute( 'hidden' );
			const virtualEl = {
				getBoundingClientRect: () => ( {
					x: event.clientX,
					y: event.clientY,
					width: 0,
					height: 0,
					top: event.clientY,
					left: event.clientX,
					right: event.clientX,
					bottom: event.clientY,
				} ),
			};
			requestAnimationFrame( () => {
				const popup = root?.querySelector( '[role="menu"]' );
				if ( popup ) {
					computePosition( virtualEl, popup, {
						placement: 'bottom-start',
						middleware: [ flip(), shift( { padding: 8 } ) ],
					} ).then( ( { x, y } ) => {
						Object.assign( popup.style, { left: x + 'px', top: y + 'px' } );
					} );
				}
				const items = root ? [ ...root.querySelectorAll( ITEM_SELECTOR ) ] : [];
				if ( items.length > 0 ) {
					items[ 0 ].focus();
					ctx.activeIndex = 0;
				} else if ( popup ) {
					popup.focus();
				}
			} );
		},
		activateItem() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = false;
			ctx.activeIndex = -1;
			const root = ref.closest( '[data-bsui-context-menu-root]' );
			const popup = root?.querySelector( '[role="menu"]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
		},
		handlePopupKeyDown( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-context-menu-root]' );
			if ( ! root ) return;
			const items = [ ...root.querySelectorAll( ITEM_SELECTOR ) ];
			if ( ! items.length ) return;

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					ctx.activeIndex = ( ctx.activeIndex + 1 ) % items.length;
					items[ ctx.activeIndex ].focus();
					break;
				case 'ArrowUp':
					event.preventDefault();
					ctx.activeIndex = ( ctx.activeIndex - 1 + items.length ) % items.length;
					items[ ctx.activeIndex ].focus();
					break;
				case 'Home':
					event.preventDefault();
					ctx.activeIndex = 0;
					items[ 0 ].focus();
					break;
				case 'End':
					event.preventDefault();
					ctx.activeIndex = items.length - 1;
					items[ ctx.activeIndex ].focus();
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					if ( ctx.activeIndex >= 0 ) items[ ctx.activeIndex ].click();
					break;
				case 'Escape':
				case 'Tab':
					event.preventDefault();
					ctx.open = false;
					ctx.activeIndex = -1;
					{
						const menuPopup = root?.querySelector( '[role="menu"]' );
						if ( menuPopup ) menuPopup.setAttribute( 'hidden', '' );
					}
					break;
			}
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				ctx.activeIndex = -1;
				const popup = ref.querySelector( '[role="menu"]' );
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
		handleDocumentKeyDown( event ) {
			if ( event.key !== 'Escape' ) return;
			const ctx = getContext();
			if ( ! ctx.open ) return;
			ctx.open = false;
			ctx.activeIndex = -1;
			const { ref } = getElement();
			const popup = ref.querySelector( '[role="menu"]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
		},
	},
} );
