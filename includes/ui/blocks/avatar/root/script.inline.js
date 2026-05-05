import { store, getContext } from '@wordpress/interactivity';

store( 'bsui/avatar', {
	state: {
		get imageHidden() {
			const { error } = getContext();
			return error;
		},
		get fallbackHidden() {
			const { loaded, error } = getContext();
			return loaded && ! error;
		},
	},
	actions: {
		handleLoad() {
			const ctx = getContext();
			ctx.loaded = true;
			ctx.error = false;
		},
		handleError() {
			const ctx = getContext();
			ctx.error = true;
		},
	},
} );
