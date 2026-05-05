import { store, getContext, getElement } from '@wordpress/interactivity';

const RADIO_SELECTOR = '[role="radio"]:not([aria-disabled="true"])';

store( 'bsui/radio-group', {
	state: {
		get selectedValue() {
			return getContext().value;
		},
		get isChecked() {
			const ctx = getContext();
			return String( ctx.radioValue ) === String( ctx.value );
		},
		get ariaChecked() {
			const ctx = getContext();
			return String( ctx.radioValue ) === String( ctx.value )
				? 'true'
				: 'false';
		},
		get tabIndex() {
			const ctx = getContext();
			return String( ctx.radioValue ) === String( ctx.value )
				? 0
				: -1;
		},
	},
	actions: {
		select() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			ctx.value = ctx.radioValue;
			const { ref } = getElement();
			ref.closest( '[data-bsui-radio-group-root]' )?.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.value } } ) );
		},
		handleKeyDown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;

			const { ref } = getElement();
			const radios = [ ...ref.querySelectorAll( RADIO_SELECTOR ) ];
			const current = radios.indexOf( document.activeElement );
			if ( current === -1 ) return;

			const vertical = ctx.orientation === 'vertical';
			const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
			const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';

			const loop = ctx.loop !== false;
			let next = -1;
			switch ( event.key ) {
				case nextKey:
					event.preventDefault();
					next = current < radios.length - 1 ? current + 1 : ( loop ? 0 : -1 );
					break;
				case prevKey:
					event.preventDefault();
					next =
						current > 0 ? current - 1 : ( loop ? radios.length - 1 : -1 );
					break;
				case 'Home':
					event.preventDefault();
					next = 0;
					break;
				case 'End':
					event.preventDefault();
					next = radios.length - 1;
					break;
			}

			if ( next >= 0 ) {
				radios[ next ].focus();
				const nextValue =
					radios[ next ].getAttribute( 'data-value' );
				if ( nextValue !== null ) {
					ctx.value = nextValue;
					ref.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: nextValue } } ) );
				}
			}
		},
	},
} );
