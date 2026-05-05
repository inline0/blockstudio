import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/date-input', {
	state: {
		get displayValue() {
			const ctx = getContext();
			if ( ! ctx.value ) return '';
			const d = new Date( ctx.value + 'T00:00:00' );
			return isNaN( d ) ? ctx.value : d.toLocaleDateString( 'en-US', { year: 'numeric', month: 'short', day: 'numeric' } );
		},
		get isoValue() {
			const ctx = getContext();
			if ( ! ctx.value ) return '';
			const d = new Date( ctx.value + 'T00:00:00' );
			if ( isNaN( d ) ) return '';
			return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = ! ctx.open;
			const root = ref.closest( '[data-bsui-date-input]' );
			const popup = root?.querySelector( '[data-bsui-date-input-popup]' );
			if ( ctx.open ) {
				if ( popup ) popup.removeAttribute( 'hidden' );
			} else {
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
		handleInput( event ) {
			const ctx = getContext();
			const val = event.target.value.trim();
			const d = new Date( val );
			if ( ! isNaN( d ) && val.length >= 8 ) {
				ctx.value = d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
				const { ref } = getElement();
				const root = ref.closest( '[data-bsui-date-input]' );
				if ( root ) root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.value } } ) );
			}
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				const popup = ref.querySelector( '[data-bsui-date-input-popup]' );
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
	},
} );

document.addEventListener( 'click', ( event ) => {
	const dayBtn = event.target.closest( '[data-bsui-calendar-grid] button[data-date]' );
	if ( ! dayBtn ) return;
	const dateInput = dayBtn.closest( '[data-bsui-date-input]' );
	if ( ! dateInput ) return;

	setTimeout( () => {
		const selected = dateInput.querySelector( '[data-bsui-calendar-grid] button[data-selected]' );
		if ( selected ) {
			const date = selected.getAttribute( 'data-date' );
			if ( date ) {
				const d = new Date( date + 'T00:00:00' );
				if ( ! isNaN( d ) ) {
					const input = dateInput.querySelector( 'input[type="text"]' );
					if ( input ) {
						input.value = d.toLocaleDateString( 'en-US', { year: 'numeric', month: 'short', day: 'numeric' } );
					}
					const hidden = dateInput.querySelector( 'input[type="hidden"]' );
					if ( hidden ) {
						hidden.value = date;
					}
				}
				const popup = dateInput.querySelector( '[data-bsui-date-input-popup]' );
				if ( popup ) popup.setAttribute( 'hidden', '' );
				dateInput.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: date } } ) );
			}
		}
	}, 50 );
} );
