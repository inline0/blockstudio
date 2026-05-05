import { computePosition, flip, shift, offset } from 'https://esm.sh/@floating-ui/dom@1.7.4';
import { store, getContext, getElement } from '@wordpress/interactivity';

const OPTION_SELECTOR =
	'[role="option"]:not([aria-disabled="true"])';
const typeahead = window.__bsui.typeahead();

function getOptions( root ) {
	return [ ...root.querySelectorAll( OPTION_SELECTOR ) ];
}

function focusOption( options, index, ctx ) {
	const result = window.__bsui.focusItem( options, index );
	if ( ctx ) {
		ctx.activeDescendant = result >= 0 ? ( options[ result ].id || '' ) : '';
	}
	return result;
}

function positionPopup( trigger, popup ) {
	const selectedOption = popup.querySelector( '[aria-selected="true"]' );

	if ( selectedOption ) {
		const rem = parseFloat( getComputedStyle( document.documentElement ).fontSize );
		const styles = getComputedStyle( document.documentElement );
		const popupPadding = parseFloat( styles.getPropertyValue( '--bs-ui-popup-padding' ) ) * rem;
		const popupBorder = 1;
		const checkmarkSpace = rem;

		const triggerRect = trigger.getBoundingClientRect();
		const optionRect = selectedOption.getBoundingClientRect();
		const popupRect = popup.getBoundingClientRect();

		const x = triggerRect.left - popupPadding - popupBorder - checkmarkSpace + 1;
		const optionOffsetY = optionRect.top - popupRect.top;
		let y = triggerRect.top - optionOffsetY;

		const maxY = window.innerHeight - popupRect.height - 8;
		y = Math.max( 8, Math.min( y, maxY ) );

		popup.style.animation = 'none';
		Object.assign( popup.style, {
			left: x + 'px',
			top: y + 'px',
			position: 'fixed',
			minWidth: ( triggerRect.width + popupPadding * 2 + popupBorder * 2 + checkmarkSpace ) + 'px',
		} );
	} else {
		computePosition( window.__bsui.getAnchor( trigger ), popup, {
			placement: 'bottom-start',
			middleware: [ offset( 4 ), flip(), shift( { padding: 8 } ) ],
		} ).then( ( { x, y } ) => {
			Object.assign( popup.style, { left: x + 'px', top: y + 'px' } );
		} );
	}
}

function isSelected( ctx, optionValue ) {
	if ( ctx.multiple ) {
		return (
			Array.isArray( ctx.value ) && ctx.value.includes( optionValue )
		);
	}
	return String( ctx.value ) === String( optionValue );
}

store( 'bsui/select', {
	state: {
		get ariaExpanded() {
			return getContext().open ? 'true' : 'false';
		},
		get displayValue() {
			const ctx = getContext();
			if ( ctx.multiple && Array.isArray( ctx.value ) ) {
				if ( ctx.value.length === 0 ) return ctx.placeholder;
				if ( ctx.labels && ctx.labels.length > 0 ) {
					return ctx.labels.join( ', ' );
				}
				return ctx.value.length + ' selected';
			}
			return ctx.label || ctx.placeholder;
		},
		get selectedValue() {
			return getContext().value;
		},
		get ariaSelected() {
			const ctx = getContext();
			return isSelected( ctx, ctx.optionValue ) ? 'true' : 'false';
		},
		get ariaMultiSelectable() {
			return getContext().multiple ? 'true' : undefined;
		},
		get activeDescendant() {
			return getContext().activeDescendant || undefined;
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = ! ctx.open;

			const root = ref.closest( '[data-bsui-select-root]' );
			const listbox = root?.querySelector( '[role="listbox"]' );

			if ( ctx.open ) {
				ctx.activeIndex = -1;
				if ( listbox ) listbox.removeAttribute( 'hidden' );
				const trigger = root?.querySelector( '[data-bsui-select-trigger]' );
				requestAnimationFrame( () => {
					if ( ! listbox ) return;

					if ( trigger ) positionPopup( trigger, listbox );
					listbox.focus();
					const options = getOptions( root );
					const currentValue = ctx.multiple
						? ( ctx.value[ 0 ] || '' )
						: ctx.value;
					const selectedIdx = options.findIndex(
						( opt ) =>
							opt.getAttribute( 'data-value' ) ===
							String( currentValue )
					);
					ctx.activeIndex = focusOption(
						options,
						selectedIdx >= 0 ? selectedIdx : 0,
						ctx
					);
				} );
			} else {
				ctx.activeDescendant = '';
				if ( listbox ) listbox.setAttribute( 'hidden', '' );
			}
		},
		selectOption() {
			const ctx = getContext();
			const { optionValue, optionLabel } = ctx;

			if ( ctx.multiple ) {
				const values = Array.isArray( ctx.value )
					? [ ...ctx.value ]
					: [];
				const labels = Array.isArray( ctx.labels )
					? [ ...ctx.labels ]
					: [];
				const idx = values.indexOf( optionValue );

				if ( idx >= 0 ) {
					values.splice( idx, 1 );
					labels.splice( idx, 1 );
				} else {
					values.push( optionValue );
					labels.push( optionLabel );
				}

				ctx.value = values;
				ctx.labels = labels;
				// Keep open in multiple mode
				return;
			}

			ctx.value = optionValue;
			ctx.label = optionLabel;
			ctx.open = false;
			ctx.activeIndex = -1;
			ctx.activeDescendant = '';

			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-select-root]' );
			const listbox = root?.querySelector( '[role="listbox"]' );
			if ( listbox ) listbox.setAttribute( 'hidden', '' );
			const trigger = root?.querySelector(
				'[data-bsui-select-trigger]'
			);
			requestAnimationFrame( () => window.__bsui.getAnchor( trigger )?.focus() );
			root?.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.value } } ) );
		},
		handleListboxKeyDown( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-select-root]' );
			if ( ! root ) return;

			const options = getOptions( root );
			if ( options.length === 0 ) return;

			const loop = ctx.loop !== false;
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					ctx.activeIndex = focusOption(
						options,
						ctx.activeIndex < options.length - 1
							? ctx.activeIndex + 1
							: loop
								? 0
								: -1,
						ctx
					);
					break;
				case 'ArrowUp':
					event.preventDefault();
					ctx.activeIndex = focusOption(
						options,
						ctx.activeIndex > 0
							? ctx.activeIndex - 1
							: loop
								? options.length - 1
								: -1,
						ctx
					);
					break;
				case 'Home':
					event.preventDefault();
					ctx.activeIndex = focusOption( options, 0, ctx );
					break;
				case 'End':
					event.preventDefault();
					ctx.activeIndex = focusOption(
						options,
						options.length - 1,
						ctx
					);
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					if (
						ctx.activeIndex >= 0 &&
						ctx.activeIndex < options.length
					) {
						options[ ctx.activeIndex ].click();
					}
					break;
				case 'Escape':
					event.preventDefault();
					ctx.open = false;
					ctx.activeIndex = -1;
					ctx.activeDescendant = '';
					{
						const listbox = root.querySelector( '[role="listbox"]' );
						if ( listbox ) listbox.setAttribute( 'hidden', '' );
						const trigger = root.querySelector(
							'[data-bsui-select-trigger]'
						);
						requestAnimationFrame( () =>
							window.__bsui.getAnchor( trigger )?.focus()
						);
					}
					break;
				case 'Tab':
					ctx.open = false;
					ctx.activeIndex = -1;
					ctx.activeDescendant = '';
					{
						const listbox = root.querySelector( '[role="listbox"]' );
						if ( listbox ) listbox.setAttribute( 'hidden', '' );
					}
					break;
				default:
					if (
						event.key.length === 1 &&
						! event.ctrlKey &&
						! event.metaKey
					) {
						const match = typeahead.handle( event.key, options );
						if ( match >= 0 ) {
							ctx.activeIndex = focusOption(
								options,
								match,
								ctx
							);
						}
					}
					break;
			}
		},
		handleTriggerKeyDown( event ) {
			const ctx = getContext();
			if (
				event.key === 'ArrowDown' ||
				event.key === 'ArrowUp' ||
				event.key === 'Enter' ||
				event.key === ' '
			) {
				event.preventDefault();
				if ( ! ctx.open ) {
					store( 'bsui/select' ).actions.toggle();
				}
			}
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;

			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				ctx.activeIndex = -1;
				ctx.activeDescendant = '';
				const listbox = ref.querySelector( '[role="listbox"]' );
				if ( listbox ) listbox.setAttribute( 'hidden', '' );
			}
		},
	},
} );
