import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/navigation-menu', {
	state: {
		get ariaExpanded() { return getContext().open ? 'true' : 'false'; },
	},
	actions: {
		open() {
			getContext().open = true;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-nav-menu]' );
			const content = root?.querySelector( '[data-bsui-nav-menu-content]' );
			if ( content ) content.removeAttribute( 'hidden' );
		},
		close() {
			getContext().open = false;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-nav-menu]' );
			const content = root?.querySelector( '[data-bsui-nav-menu-content]' );
			if ( content ) content.setAttribute( 'hidden', '' );
		},
		toggle() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = ! ctx.open;
			const root = ref.closest( '[data-bsui-nav-menu]' );
			const content = root?.querySelector( '[data-bsui-nav-menu-content]' );
			if ( ctx.open ) {
				if ( content ) content.removeAttribute( 'hidden' );
			} else {
				if ( content ) content.setAttribute( 'hidden', '' );
			}
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				const content = ref.querySelector( '[data-bsui-nav-menu-content]' );
				if ( content ) content.setAttribute( 'hidden', '' );
			}
		},
	},
} );
