import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/toolbar', {
	actions: {
		handleKeyDown( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const items = [ ...ref.querySelectorAll( window.__bsui.FOCUSABLE ) ];
			const current = items.indexOf( document.activeElement );
			if ( current === -1 ) return;

			const vertical = ctx.orientation === 'vertical';
			const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
			const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';

			let next = -1;
			switch ( event.key ) {
				case nextKey:
					event.preventDefault();
					next = current < items.length - 1 ? current + 1 : 0;
					break;
				case prevKey:
					event.preventDefault();
					next = current > 0 ? current - 1 : items.length - 1;
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
