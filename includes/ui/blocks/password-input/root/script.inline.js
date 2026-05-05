import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/password-input', {
	state: {
		get inputType() {
			return getContext().visible ? 'text' : 'password';
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			ctx.visible = ! ctx.visible;
		},
		handleInput( event ) {
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-password-input]' );
			if ( root ) {
				root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: event.target.value } } ) );
			}
		},
	},
} );
