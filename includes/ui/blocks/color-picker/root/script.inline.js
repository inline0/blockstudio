import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/color-picker', {
	actions: {
		handleColorInput( event ) {
			const ctx = getContext();
			ctx.value = event.target.value;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-color-picker]' );
			const swatch = root?.querySelector( '[data-bsui-color-swatch]' );
			if ( swatch ) swatch.style.backgroundColor = ctx.value;
			const text = root?.querySelector( 'input[type="text"]' );
			if ( text ) text.value = ctx.value;
			root?.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.value } } ) );
		},
		handleTextInput( event ) {
			const ctx = getContext();
			const val = event.target.value;
			if ( /^#[0-9a-fA-F]{6}$/.test( val ) ) {
				ctx.value = val;
				const { ref } = getElement();
				const root = ref.closest( '[data-bsui-color-picker]' );
				const swatch = root?.querySelector( '[data-bsui-color-swatch]' );
				if ( swatch ) swatch.style.backgroundColor = val;
				const color = root?.querySelector( 'input[type="color"]' );
				if ( color ) color.value = val;
				root?.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: val } } ) );
			}
		},
	},
} );
