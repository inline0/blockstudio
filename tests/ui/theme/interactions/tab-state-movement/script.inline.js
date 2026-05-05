import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'interaction/tab-state-movement', {
	state: {
		get activeItems() {
			return getContext().items.filter( ( item ) => ! item.archived );
		},
		get archivedItems() {
			return getContext().items.filter( ( item ) => item.archived );
		},
		get activeCount() {
			return getContext().items.filter( ( item ) => ! item.archived ).length;
		},
		get archivedCount() {
			return getContext().items.filter( ( item ) => item.archived ).length;
		},
	},
	actions: {
		showActive() {
			getContext().activeTab = 'active';
		},
		showArchived() {
			getContext().activeTab = 'archived';
		},
		toggleArchived() {
			const ctx = getContext();
			const { ref } = getElement();
			const itemId = parseInt( ref.dataset.itemId, 10 );
			ctx.items = ctx.items.map( ( item ) =>
				item.id === itemId ? { ...item, archived: ! item.archived } : item
			);
		},
	},
} );
