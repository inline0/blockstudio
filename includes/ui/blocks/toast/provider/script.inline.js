import { store, getContext } from '@wordpress/interactivity';

store( 'bsui/toast', {
	state: {
		get hasToasts() {
			return getContext().toasts.length > 0;
		},
	},
	actions: {
		dismiss() {
			const ctx = getContext();
			ctx.toasts = ctx.toasts.filter( ( t ) => t.id !== ctx.toastId );
		},
	},
} );
