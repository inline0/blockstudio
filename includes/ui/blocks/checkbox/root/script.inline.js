import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/checkbox', {
	state: {
		get ariaChecked() {
			const ctx = getContext();
			if ( ctx.indeterminate ) return 'mixed';
			return ctx.checked ? 'true' : 'false';
		},
		get formValue() {
			const ctx = getContext();
			if ( ctx.indeterminate ) return '';
			return ctx.checked ? 'on' : '';
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			if ( ctx.indeterminate ) {
				ctx.indeterminate = false;
				ctx.checked = true;
			} else {
				ctx.checked = ! ctx.checked;
			}
			const { ref } = getElement();
			ref.closest( '[data-bsui-checkbox]' )?.dispatchEvent(
				new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.checked } } )
			);
		},
	},
} );
