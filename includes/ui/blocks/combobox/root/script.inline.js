import { computePosition, flip, shift, offset } from 'https://esm.sh/@floating-ui/dom@1.7.4';
import { store, getContext, getElement } from '@wordpress/interactivity';

const OPTION_SELECTOR = '[role="option"]:not([aria-disabled="true"])';

function getVisibleOptions( root ) {
	return [ ...root.querySelectorAll( OPTION_SELECTOR ) ].filter( ( el ) => ! el.hidden );
}

function positionPopup( input, popup ) {
	computePosition( input, popup, {
		placement: 'bottom-start',
		middleware: [ offset( 4 ), flip(), shift( { padding: 8 } ) ],
	} ).then( ( { x, y } ) => {
		Object.assign( popup.style, { left: x + 'px', top: y + 'px' } );
	} );
}

store( 'bsui/combobox', {
	state: {
		get selectedValue() { return getContext().value; },
	},
	actions: {
		handleInput( event ) {
			const ctx = getContext();
			const query = event.target.value.toLowerCase();
			ctx.query = query;
			ctx.open = true;
			ctx.activeIndex = -1;

			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-combobox-root]' );
			const options = root?.querySelectorAll( OPTION_SELECTOR );
			if ( options ) {
				options.forEach( ( opt ) => {
					const text = opt.textContent?.trim().toLowerCase() || '';
					opt.hidden = query !== '' && ! text.includes( query );
				} );
			}

			const input = root?.querySelector( '[data-bsui-combobox-input]' );
			const popup = root?.querySelector( '[data-bsui-combobox-popup]' );
			if ( popup ) popup.removeAttribute( 'hidden' );
			if ( input && popup ) {
				requestAnimationFrame( () => positionPopup( input, popup ) );
			}
		},
		handleFocus() {
			const ctx = getContext();
			ctx.open = true;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-combobox-root]' );
			const input = root?.querySelector( '[data-bsui-combobox-input]' );
			const popup = root?.querySelector( '[data-bsui-combobox-popup]' );
			if ( popup ) popup.removeAttribute( 'hidden' );
			if ( input && popup ) {
				requestAnimationFrame( () => positionPopup( input, popup ) );
			}
		},
		selectOption() {
			const ctx = getContext();
			ctx.value = ctx.optionValue;
			ctx.label = ctx.optionLabel;
			ctx.open = false;
			ctx.query = '';

			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-combobox-root]' );
			const popup = root?.querySelector( '[data-bsui-combobox-popup]' );
			if ( popup ) popup.setAttribute( 'hidden', '' );
			const input = root?.querySelector( '[data-bsui-combobox-input]' );
			if ( input ) {
				input.value = ctx.optionLabel;
			}
			const options = root?.querySelectorAll( OPTION_SELECTOR );
			if ( options ) {
				options.forEach( ( opt ) => { opt.hidden = false; } );
			}
		},
		handleKeyDown( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-combobox-root]' );
			if ( ! root ) return;

			const options = getVisibleOptions( root );
			if ( ! options.length && event.key !== 'Escape' ) return;

			const popup = root?.querySelector( '[data-bsui-combobox-popup]' );
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					ctx.open = true;
					if ( popup ) popup.removeAttribute( 'hidden' );
					ctx.activeIndex = ctx.activeIndex < options.length - 1 ? ctx.activeIndex + 1 : 0;
					options[ ctx.activeIndex ]?.focus();
					break;
				case 'ArrowUp':
					event.preventDefault();
					ctx.activeIndex = ctx.activeIndex > 0 ? ctx.activeIndex - 1 : options.length - 1;
					options[ ctx.activeIndex ]?.focus();
					break;
				case 'Enter':
					event.preventDefault();
					if ( ctx.activeIndex >= 0 && ctx.activeIndex < options.length ) {
						options[ ctx.activeIndex ].click();
					}
					break;
				case 'Escape':
					event.preventDefault();
					ctx.open = false;
					ctx.activeIndex = -1;
					if ( popup ) popup.setAttribute( 'hidden', '' );
					root.querySelector( '[data-bsui-combobox-input]' )?.focus();
					break;
			}
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				ctx.activeIndex = -1;
				const popup = ref.querySelector( '[data-bsui-combobox-popup]' );
				if ( popup ) popup.setAttribute( 'hidden', '' );
			}
		},
	},
} );
