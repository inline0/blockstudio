import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'interaction/checkbox-counter-sync', {
	state: {
		get doneCount() {
			return getContext().items.filter( ( item ) => item.done ).length;
		},
		get totalCount() {
			return getContext().items.length;
		},
	},
	actions: {
		toggleItem() {
			const ctx = getContext();
			const { ref } = getElement();
			const itemId = parseInt( ref.dataset.itemId, 10 );
			ctx.items = ctx.items.map( ( item ) =>
				item.id === itemId ? { ...item, done: ! item.done } : item
			);
		},
		completeAll() {
			const ctx = getContext();
			ctx.items = ctx.items.map( ( item ) => ( { ...item, done: true } ) );
		},
		resetAll() {
			const ctx = getContext();
			ctx.items = ctx.items.map( ( item ) => ( { ...item, done: false } ) );
		},
	},
} );
