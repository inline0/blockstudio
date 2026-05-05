import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/calendar', {
	state: {
		get selectedValue() {
			return getContext().selected;
		},
	},
	actions: {
		selectDay( event ) {
			const btn = event.target.closest( '[data-date]' );
			if ( ! btn ) return;
			const ctx = getContext();
			const { ref } = getElement();

			ref.querySelectorAll( '[data-selected]' ).forEach( ( el ) => {
				el.removeAttribute( 'data-selected' );
			} );

			btn.setAttribute( 'data-selected', '' );
			ctx.selected = btn.getAttribute( 'data-date' );
		},
	},
} );
