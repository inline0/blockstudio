import { store, getContext, getElement } from '@wordpress/interactivity';

function clamp( value, min, max ) {
	if ( value < min ) return min;
	if ( value > max ) return max;
	return value;
}

function pad( n ) {
	return n !== null ? String( n ).padStart( 2, '0' ) : '';
}

function fireChange( ref, ctx ) {
	var root = ref.closest( '[data-bsui-time-picker]' );
	if ( ! root ) return;
	var value = ( ctx.hour !== null && ctx.minute !== null )
		? pad( ctx.hour ) + ':' + pad( ctx.minute )
		: '';
	root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: value } } ) );
}

store( 'bsui/time-picker', {
	state: {
		get displayHour() {
			const { hour } = getContext();
			return hour !== null ? pad( hour ) : '';
		},
		get displayMinute() {
			const { minute } = getContext();
			return minute !== null ? pad( minute ) : '';
		},
		get formValue() {
			const { hour, minute } = getContext();
			if ( hour === null || minute === null ) return '';
			return pad( hour ) + ':' + pad( minute );
		},
	},
	actions: {
		handleHourInput() {
			const ctx = getContext();
			const { ref } = getElement();
			const raw = ref.value.replace( /[^0-9]/g, '' );

			if ( raw === '' ) {
				ctx.hour = null;
				ref.value = '';
				return;
			}

			const num = clamp( parseInt( raw, 10 ), 0, 23 );
			ctx.hour = num;

			if ( raw.length >= 2 || num > 2 ) {
				ref.value = pad( num );
				fireChange( ref, ctx );
				const root = ref.closest( '[data-bsui-time-picker]' );
				const minuteInput = root.querySelector( '[data-bsui-time-minute]' );
				if ( minuteInput ) {
					minuteInput.focus();
					minuteInput.select();
				}
			}
		},
		handleMinuteInput() {
			const ctx = getContext();
			const { ref } = getElement();
			const raw = ref.value.replace( /[^0-9]/g, '' );

			if ( raw === '' ) {
				ctx.minute = null;
				ref.value = '';
				return;
			}

			const num = clamp( parseInt( raw, 10 ), 0, 59 );
			ctx.minute = num;

			if ( raw.length >= 2 ) {
				ref.value = pad( num );
				fireChange( ref, ctx );
			}
		},
		handleHourKeydown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			const { ref } = getElement();
			const step = event.shiftKey ? 10 : 1;

			if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				ctx.hour = ctx.hour !== null ? ( ctx.hour + step ) % 24 : 0;
				ref.value = pad( ctx.hour );
				fireChange( ref, ctx );
			} else if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				ctx.hour = ctx.hour !== null ? ( ctx.hour - step + 24 ) % 24 : 23;
				ref.value = pad( ctx.hour );
				fireChange( ref, ctx );
			}
		},
		handleMinuteKeydown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			const { ref } = getElement();
			const step = event.shiftKey ? 10 : ctx.step;

			if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				ctx.minute = ctx.minute !== null ? ( ctx.minute + step ) % 60 : 0;
				ref.value = pad( ctx.minute );
				fireChange( ref, ctx );
			} else if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				ctx.minute = ctx.minute !== null ? ( ctx.minute - step + 60 ) % 60 : 59;
				ref.value = pad( ctx.minute );
				fireChange( ref, ctx );
			} else if ( event.key === 'Backspace' && ref.value === '' ) {
				event.preventDefault();
				const root = ref.closest( '[data-bsui-time-picker]' );
				const hourInput = root.querySelector( '[data-bsui-time-hour]' );
				if ( hourInput ) {
					hourInput.focus();
					hourInput.select();
				}
			}
		},
		selectAll() {
			const { ref } = getElement();
			ref.select();
		},
		padHour() {
			const ctx = getContext();
			const { ref } = getElement();
			if ( ctx.hour !== null ) {
				ref.value = pad( ctx.hour );
			}
		},
		padMinute() {
			const ctx = getContext();
			const { ref } = getElement();
			if ( ctx.minute !== null ) {
				ref.value = pad( ctx.minute );
			}
		},
	},
} );
