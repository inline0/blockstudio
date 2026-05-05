import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'interaction/confirmation-delete', {
	state: {
		get dialogMessage() {
			return 'Are you sure you want to delete ' + state.pendingName + '?';
		},
	},
	actions: {
		requestDelete() {
			var ctx = getContext();
			state.pendingId = ctx.item.id;
			state.pendingName = ctx.item.name;
			state.dialogOpen = true;
		},
		confirmDelete() {
			state.items = state.items.filter( function ( item ) {
				return item.id !== state.pendingId;
			} );
			state.pendingId = '';
			state.pendingName = '';
			state.dialogOpen = false;
		},
		cancelDelete() {
			state.pendingId = '';
			state.pendingName = '';
			state.dialogOpen = false;
		},
	},
} );
