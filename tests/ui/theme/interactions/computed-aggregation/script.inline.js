import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'interaction/computed-aggregation', {
	state: {
		get totalItems() {
			var total = 0;
			for ( var i = 0; i < state.products.length; i++ ) {
				total += state.products[ i ].quantity;
			}
			return total;
		},
		get totalValue() {
			var total = 0;
			for ( var i = 0; i < state.products.length; i++ ) {
				total += state.products[ i ].quantity * state.products[ i ].price;
			}
			return Math.round( total * 100 ) / 100;
		},
		get averagePrice() {
			var items = state.totalItems;
			if ( items === 0 ) return 0;
			return Math.round( ( state.totalValue / items ) * 100 ) / 100;
		},
	},
	actions: {
		incrementQty() {
			var ctx = getContext();
			ctx.product.quantity = ctx.product.quantity + 1;
			ctx.product.subtotal = ctx.product.quantity * ctx.product.price;
		},
		decrementQty() {
			var ctx = getContext();
			if ( ctx.product.quantity > 0 ) {
				ctx.product.quantity = ctx.product.quantity - 1;
				ctx.product.subtotal = ctx.product.quantity * ctx.product.price;
			}
		},
	},
} );
