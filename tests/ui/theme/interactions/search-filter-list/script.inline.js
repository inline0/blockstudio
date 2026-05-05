import { store, getContext } from '@wordpress/interactivity';

const allItems = [ 'Apple', 'Banana', 'Cherry', 'Date', 'Elderberry', 'Fig', 'Grape' ];

const { state } = store( 'interaction/search-filter-list', {
	actions: {
		updateSearch( event ) {
			var ctx = getContext();
			ctx.search = event.target.value;
			var term = ctx.search.toLowerCase().trim();
			if ( term === '' ) {
				state.visibleItems = allItems.slice();
			} else {
				state.visibleItems = allItems.filter( function ( item ) {
					return item.toLowerCase().includes( term );
				} );
			}
			state.visibleCount = state.visibleItems.length;
			state.hasResults = state.visibleItems.length > 0;
		},
	},
} );
