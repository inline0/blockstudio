import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/toggle', {
	state: {
		get ariaPressed() {
			const { pressed } = getContext();
			return pressed ? 'true' : 'false';
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			ctx.pressed = ! ctx.pressed;
			const { ref } = getElement();
			ref.closest( '[data-bsui-toggle]' )?.dispatchEvent(
				new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.pressed } } )
			);
		},
	},
} );
