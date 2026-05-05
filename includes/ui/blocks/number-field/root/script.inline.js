import { store, getContext, getElement } from '@wordpress/interactivity';

function clamp( value, min, max ) {
	if ( min !== null && value < min ) return min;
	if ( max !== null && value > max ) return max;
	return value;
}

function dispatchChange( value ) {
	getElement().ref.closest( '[role="group"]' ).dispatchEvent(
		new CustomEvent( 'change', { bubbles: true, detail: { value } } )
	);
}

store( 'bsui/number-field', {
	state: {
		get displayValue() {
			const { value } = getContext();
			return value !== null ? String( value ) : '';
		},
	},
	actions: {
		increment() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			const current = ctx.value !== null ? ctx.value : 0;
			ctx.value = clamp( current + ctx.step, ctx.min, ctx.max );
			dispatchChange( ctx.value );
		},
		decrement() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			const current = ctx.value !== null ? ctx.value : 0;
			ctx.value = clamp( current - ctx.step, ctx.min, ctx.max );
			dispatchChange( ctx.value );
		},
		handleInput( event ) {
			const ctx = getContext();
			const parsed = parseFloat( event.target.value );
			if ( ! isNaN( parsed ) ) {
				ctx.value = clamp( parsed, ctx.min, ctx.max );
				dispatchChange( ctx.value );
			}
		},
		handleKeyDown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;

			const multiplier = event.shiftKey ? 10 : 1;
			const delta = ctx.step * multiplier;

			if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				const current = ctx.value !== null ? ctx.value : 0;
				ctx.value = clamp( current + delta, ctx.min, ctx.max );
				dispatchChange( ctx.value );
			} else if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				const current = ctx.value !== null ? ctx.value : 0;
				ctx.value = clamp( current - delta, ctx.min, ctx.max );
				dispatchChange( ctx.value );
			}
		},
	},
} );
