import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'interaction/dialog-form-select', {
	actions: {
		openDialog() {
			getContext().dialogOpen = true;
		},
		closeDialog() {
			const ctx = getContext();
			ctx.dialogOpen = false;
			ctx.itemName = '';
			ctx.itemCategory = '';
			ctx.selectOpen = false;
		},
		setItemName( event ) {
			getContext().itemName = event.target.value;
		},
		toggleSelect() {
			getContext().selectOpen = ! getContext().selectOpen;
		},
		selectCategory() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.itemCategory = ref.dataset.value;
			ctx.selectOpen = false;
		},
		submitItem( event ) {
			event.preventDefault();
			const ctx = getContext();
			if ( ! ctx.itemName || ! ctx.itemCategory ) return;

			ctx.items = [
				...ctx.items,
				{ name: ctx.itemName, category: ctx.itemCategory },
			];

			ctx.itemName = '';
			ctx.itemCategory = '';
			ctx.selectOpen = false;
			ctx.dialogOpen = false;
		},
	},
} );
