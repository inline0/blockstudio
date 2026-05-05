import { store, getContext, getElement } from '@wordpress/interactivity';

function setSliderValue( ctx, el, val ) {
	ctx.value = val;
	var p = ctx.max > ctx.min ? ( ( val - ctx.min ) / ( ctx.max - ctx.min ) ) * 100 : 0;
	el.style.setProperty( '--bs-ui-slider-percent', p + '%' );
	el.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: val } } ) );
}

function clampStep( val, min, max, step ) {
	return Math.round( Math.max( min, Math.min( max, val ) ) / step ) * step;
}

store( 'bsui/slider', {
	state: {
		get currentValue() {
			return getContext().value;
		},
		get percent() {
			const { value, min, max } = getContext();
			return max > min ? ( ( value - min ) / ( max - min ) ) * 100 : 0;
		},
		get percentStr() {
			const { value, min, max } = getContext();
			const p = max > min ? ( ( value - min ) / ( max - min ) ) * 100 : 0;
			return p + '%';
		},
		get valueText() {
			return String( getContext().value );
		},
	},
	actions: {
		handleKeyDown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;

			const { value, min, max, step } = ctx;

			let newValue = value;
			switch ( event.key ) {
				case 'ArrowRight':
				case 'ArrowUp':
					event.preventDefault();
					newValue = Math.min( max, value + step );
					break;
				case 'ArrowLeft':
				case 'ArrowDown':
					event.preventDefault();
					newValue = Math.max( min, value - step );
					break;
				case 'Home':
					event.preventDefault();
					newValue = min;
					break;
				case 'End':
					event.preventDefault();
					newValue = max;
					break;
				case 'PageUp':
					event.preventDefault();
					newValue = Math.min( max, value + step * 10 );
					break;
				case 'PageDown':
					event.preventDefault();
					newValue = Math.max( min, value - step * 10 );
					break;
				default:
					return;
			}
			setSliderValue( ctx, event.currentTarget, Math.round( newValue / step ) * step );
		},
		handlePointerDown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;

			const el = event.currentTarget;
			const vertical = ctx.orientation === 'vertical';

			function posToValue( e ) {
				var r = el.getBoundingClientRect();
				var pos = vertical
					? 1 - ( e.clientY - r.top ) / r.height
					: ( e.clientX - r.left ) / r.width;
				return clampStep( ctx.min + pos * ( ctx.max - ctx.min ), ctx.min, ctx.max, ctx.step );
			}

			setSliderValue( ctx, el, posToValue( event ) );

			var onMove = function ( e ) {
				setSliderValue( ctx, el, posToValue( e ) );
			};
			var onUp = function () {
				document.removeEventListener( 'pointermove', onMove );
				document.removeEventListener( 'pointerup', onUp );
			};
			document.addEventListener( 'pointermove', onMove );
			document.addEventListener( 'pointerup', onUp );
		},
	},
	callbacks: {
		syncPercent() {
			var ctx = getContext();
			var p = ctx.max > ctx.min ? ( ( ctx.value - ctx.min ) / ( ctx.max - ctx.min ) ) * 100 : 0;
			getElement().ref.style.setProperty( '--bs-ui-slider-percent', p + '%' );
		},
	},
} );
