import { store, getContext, getElement } from '@wordpress/interactivity';

function getOptions( root ) {
	return [ ...root.querySelectorAll( '[data-bsui-phone-option]' ) ];
}

function getVisibleOptions( root ) {
	return getOptions( root ).filter( ( o ) => ! o.hidden );
}

function getPopup( root ) {
	return root.querySelector( '[data-bsui-phone-popup]' );
}

function showPopup( root ) {
	const trigger = root.querySelector( '[data-bsui-phone-trigger]' );
	const popup = getPopup( root );
	if ( ! trigger || ! popup ) return;
	popup.hidden = false;
	popup.style.top = trigger.offsetHeight + 'px';
	popup.style.left = '0';
	popup.style.minWidth = root.offsetWidth + 'px';
}

function hidePopup( root ) {
	const popup = getPopup( root );
	if ( popup ) popup.hidden = true;
}

store( 'bsui/phone-input', {
	state: {
		get formValue() {
			const ctx = getContext();
			if ( ! ctx.number ) return '';
			return ctx.code + ctx.number;
		},
		get triggerLabel() {
			const ctx = getContext();
			return ctx.countryCode + ' ' + ctx.code;
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-phone-input]' );
			ctx.open = ! ctx.open;
			if ( ctx.open ) {
				ctx.query = '';
				ctx.activeIndex = -1;
				getOptions( root ).forEach( ( o ) => {
					o.hidden = false;
				} );
				showPopup( root );
				requestAnimationFrame( () => {
					const input = root.querySelector( '[data-bsui-phone-search-input]' );
					if ( input ) input.focus();
				} );
			} else {
				hidePopup( root );
			}
		},
		handleTriggerKeydown( event ) {
			if ( event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				const ctx = getContext();
				if ( ! ctx.open ) {
					const { ref } = getElement();
					const root = ref.closest( '[data-bsui-phone-input]' );
					ctx.open = true;
					ctx.query = '';
					ctx.activeIndex = -1;
					getOptions( root ).forEach( ( o ) => {
						o.hidden = false;
					} );
					showPopup( root );
					requestAnimationFrame( () => {
						const input = root.querySelector( '[data-bsui-phone-search-input]' );
						if ( input ) input.focus();
					} );
				}
			}
		},
		handleSearch( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-phone-input]' );
			ctx.query = event.target.value.toLowerCase();
			ctx.activeIndex = -1;

			getOptions( root ).forEach( ( o ) => {
				const label = o.getAttribute( 'data-label' ).toLowerCase();
				const dial = o.getAttribute( 'data-dial' );
				const code = o.getAttribute( 'data-country' ).toLowerCase();
				const match = label.includes( ctx.query ) ||
					dial.includes( ctx.query ) ||
					code.includes( ctx.query );
				o.hidden = ! match;
				o.removeAttribute( 'data-active' );
			} );
		},
		handleSearchKeydown( event ) {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-phone-input]' );
			const visible = getVisibleOptions( root );

			if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				ctx.activeIndex = Math.min( ctx.activeIndex + 1, visible.length - 1 );
				visible.forEach( ( o, i ) => {
					if ( i === ctx.activeIndex ) {
						o.setAttribute( 'data-active', '' );
						o.scrollIntoView( { block: 'nearest' } );
					} else {
						o.removeAttribute( 'data-active' );
					}
				} );
			} else if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				ctx.activeIndex = Math.max( ctx.activeIndex - 1, 0 );
				visible.forEach( ( o, i ) => {
					if ( i === ctx.activeIndex ) {
						o.setAttribute( 'data-active', '' );
						o.scrollIntoView( { block: 'nearest' } );
					} else {
						o.removeAttribute( 'data-active' );
					}
				} );
			} else if ( event.key === 'Enter' ) {
				event.preventDefault();
				if ( ctx.activeIndex >= 0 && ctx.activeIndex < visible.length ) {
					const opt = visible[ ctx.activeIndex ];
					ctx.code = opt.getAttribute( 'data-dial' );
					ctx.countryCode = opt.getAttribute( 'data-country' );
					ctx.open = false;
					hidePopup( root );
					const value = ctx.number ? ctx.code + ctx.number : '';
					root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: value } } ) );
					const telInput = root.querySelector( 'input[type="tel"]' );
					if ( telInput ) telInput.focus();
				}
			} else if ( event.key === 'Escape' ) {
				event.preventDefault();
				ctx.open = false;
				hidePopup( root );
				const trigger = root.querySelector( '[data-bsui-phone-trigger]' );
				if ( trigger ) trigger.focus();
			}
		},
		selectCountry() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-phone-input]' );
			ctx.code = ref.getAttribute( 'data-dial' );
			ctx.countryCode = ref.getAttribute( 'data-country' );
			ctx.open = false;
			hidePopup( root );
			const value = ctx.number ? ctx.code + ctx.number : '';
			root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: value } } ) );
			const telInput = root.querySelector( 'input[type="tel"]' );
			if ( telInput ) telInput.focus();
		},
		setNumber( event ) {
			const ctx = getContext();
			ctx.number = event.target.value;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-phone-input]' );
			const value = ctx.number ? ctx.code + ctx.number : '';
			if ( root ) root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: value } } ) );
		},
		handleOutsideClick( event ) {
			const ctx = getContext();
			if ( ! ctx.open ) return;
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				ctx.open = false;
				hidePopup( ref );
			}
		},
	},
} );
