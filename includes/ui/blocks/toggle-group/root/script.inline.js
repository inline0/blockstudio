import { store, getContext, getElement } from '@wordpress/interactivity';

const ITEM_SELECTOR = '[data-bsui-toggle-group-item]:not([disabled])';

store( 'bsui/toggle-group', {
	state: {
		get isPressed() {
			const ctx = getContext();
			return Array.isArray( ctx.value ) && ctx.value.includes( ctx.itemValue );
		},
		get ariaPressed() {
			const ctx = getContext();
			return Array.isArray( ctx.value ) && ctx.value.includes( ctx.itemValue )
				? 'true'
				: 'false';
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			const { value, multiple, itemValue } = ctx;
			const isPressed = value.includes( itemValue );

			if ( ! multiple ) {
				ctx.value = isPressed ? [] : [ itemValue ];
			} else if ( isPressed ) {
				ctx.value = value.filter( ( v ) => v !== itemValue );
			} else {
				ctx.value = [ ...value, itemValue ];
			}
		},
		handleKeyDown( event ) {
			const { ref } = getElement();
			if ( ref.closest( '[role="toolbar"]' ) ) return;
			const items = [ ...ref.querySelectorAll( ITEM_SELECTOR ) ];
			const current = items.indexOf( document.activeElement );
			if ( current === -1 ) return;

			const ctx = getContext();
			const vertical = ctx.orientation === 'vertical';
			const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
			const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';

			const loop = ctx.loop !== false;
			let next = -1;
			switch ( event.key ) {
				case nextKey:
					event.preventDefault();
					next = current < items.length - 1 ? current + 1 : ( loop ? 0 : -1 );
					break;
				case prevKey:
					event.preventDefault();
					next = current > 0 ? current - 1 : ( loop ? items.length - 1 : -1 );
					break;
				case 'Home':
					event.preventDefault();
					next = 0;
					break;
				case 'End':
					event.preventDefault();
					next = items.length - 1;
					break;
			}
			if ( next >= 0 ) items[ next ].focus();
		},
	},
} );
