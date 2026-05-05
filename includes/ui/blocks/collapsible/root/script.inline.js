import { store, getContext } from '@wordpress/interactivity';

store( 'bsui/collapsible', {
	state: {
		get ariaExpanded() {
			const { open } = getContext();
			return open ? 'true' : 'false';
		},
		get panelHidden() {
			const { open } = getContext();
			return ! open;
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			ctx.open = ! ctx.open;
		},
	},
} );
