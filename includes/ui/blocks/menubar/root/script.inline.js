import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/menubar', {
	actions: {
		toggle() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = ! ctx.open;
			const root = ref.closest( '[data-bsui-menubar-menu]' );
			const popup = root?.querySelector( '[data-bsui-menubar-popup]' );
			if ( ctx.open ) {
				if ( popup ) popup.removeAttribute( 'hidden' );
			} else {
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
		close() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = false;
			const root = ref.closest( '[data-bsui-menubar-menu]' );
			const popup = root?.querySelector( '[data-bsui-menubar-popup]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
		},
		openSub() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.subOpen = true;
			const submenu = ref.closest( '[data-bsui-menubar-submenu]' );
			const subPopup = submenu?.querySelector( '[data-bsui-menubar-submenu-popup]' );
			if ( subPopup ) subPopup.removeAttribute( 'hidden' );
		},
		closeSub() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.subOpen = false;
			const submenu = ref.closest( '[data-bsui-menubar-submenu]' );
			const subPopup = submenu?.querySelector( '[data-bsui-menubar-submenu-popup]' );
			if ( subPopup ) subPopup.setAttribute( 'hidden', '' );
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				const popup = ref.querySelector( '[data-bsui-menubar-popup]' );
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
	},
} );
