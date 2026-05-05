import { store, getContext, getElement } from '@wordpress/interactivity';
import { computePosition, flip, shift, offset } from 'https://esm.sh/@floating-ui/dom@1.7.4';

const ITEM_SELECTOR = '[role="menuitem"]:not([aria-disabled="true"])';
const typeahead = window.__bsui.typeahead();

function getItems( root ) {
	return [ ...root.querySelectorAll( ITEM_SELECTOR ) ];
}

function positionPopup( trigger, popup ) {
	computePosition( window.__bsui.getAnchor( trigger ), popup, {
		placement: 'bottom-start',
		middleware: [ offset( 4 ), flip(), shift( { padding: 8 } ) ],
	} ).then( ( { x, y } ) => {
		Object.assign( popup.style, {
			left: x + 'px',
			top: y + 'px',
		} );
	} );
}

store( 'bsui/menu', {
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

			const root = ref.closest( '[data-bsui-menu-root]' );
			const popup = root?.querySelector( '[role="menu"]' );

			if ( ctx.open ) {
				ctx.activeIndex = -1;
				if ( popup ) popup.removeAttribute( 'hidden' );
				const trigger = root?.querySelector( '[data-bsui-menu-trigger]' );
				requestAnimationFrame( () => {
					if ( popup && trigger ) {
						positionPopup( trigger, popup );
						popup.focus();
					}
				} );
			} else {
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
		handleTriggerKeyDown( event ) {
			const ctx = getContext();

			if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
				event.preventDefault();

				if ( ! ctx.open ) {
					ctx.open = true;
					ctx.activeIndex = -1;
					const { ref } = getElement();
					const root = ref.closest( '[data-bsui-menu-root]' );
					const popup = root?.querySelector( '[role="menu"]' );
					if ( popup ) popup.removeAttribute( 'hidden' );
					const trigger = root?.querySelector( '[data-bsui-menu-trigger]' );
					requestAnimationFrame( () => {
						if ( popup && trigger ) {
							positionPopup( trigger, popup );
							const items = getItems( root );
							if ( items.length > 0 ) {
								const startIndex = event.key === 'ArrowUp' ? items.length - 1 : 0;
								ctx.activeIndex = window.__bsui.focusItem( items, startIndex );
							} else {
								popup.focus();
							}
						}
					} );
				}
			}
		},
		activateItem() {
			const { ref } = getElement();
			if ( ref.getAttribute( 'aria-disabled' ) === 'true' ) {
				return;
			}

			const ctx = getContext();
			ctx.open = false;

			const root = ref.closest( '[data-bsui-menu-root]' );
			const popup = root?.querySelector( '[role="menu"]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
			const trigger = root?.querySelector( '[data-bsui-menu-trigger]' );
			requestAnimationFrame( () => window.__bsui.getAnchor( trigger )?.focus() );
		},
		handlePopupKeyDown( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-menu-root]' );
			if ( ! root ) return;

			const items = getItems( root );

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					if ( items.length === 0 ) break;
					ctx.activeIndex = window.__bsui.focusItem(
						items,
						ctx.activeIndex < items.length - 1 ? ctx.activeIndex + 1 : 0
					);
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( items.length === 0 ) break;
					ctx.activeIndex = window.__bsui.focusItem(
						items,
						ctx.activeIndex > 0 ? ctx.activeIndex - 1 : items.length - 1
					);
					break;
				case 'Home':
					event.preventDefault();
					if ( items.length === 0 ) break;
					ctx.activeIndex = window.__bsui.focusItem( items, 0 );
					break;
				case 'End':
					event.preventDefault();
					if ( items.length === 0 ) break;
					ctx.activeIndex = window.__bsui.focusItem( items, items.length - 1 );
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					if ( ctx.activeIndex >= 0 && ctx.activeIndex < items.length ) {
						items[ ctx.activeIndex ].click();
					}
					break;
				case 'Escape':
					event.preventDefault();
					ctx.open = false;
					ctx.activeIndex = -1;
					{
						const menuPopup = root.querySelector( '[role="menu"]' );
						if ( menuPopup ) menuPopup.setAttribute( 'hidden', '' );
						const trigger = root.querySelector( '[data-bsui-menu-trigger]' );
						requestAnimationFrame( () => window.__bsui.getAnchor( trigger )?.focus() );
					}
					break;
				case 'Tab':
					ctx.open = false;
					ctx.activeIndex = -1;
					{
						const menuPopup = root.querySelector( '[role="menu"]' );
						if ( menuPopup ) menuPopup.setAttribute( 'hidden', '' );
					}
					break;
				default:
					if ( items.length > 0 && event.key.length === 1 && ! event.ctrlKey && ! event.metaKey ) {
						const match = typeahead.handle( event.key, items );
						if ( match >= 0 ) {
							ctx.activeIndex = window.__bsui.focusItem( items, match );
						}
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
			const trigger = ref.querySelector( '[data-bsui-menu-trigger]' );
			requestAnimationFrame( () => window.__bsui.getAnchor( trigger )?.focus() );
		},
	},
} );
