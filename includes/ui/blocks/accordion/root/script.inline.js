import { store, getContext, getElement } from '@wordpress/interactivity';

function animatePanel( panel, open ) {
	if ( ! panel ) return;

	if ( panel._onTransitionEnd ) {
		panel.removeEventListener( 'transitionend', panel._onTransitionEnd );
		panel._onTransitionEnd = null;
	}

	if ( open ) {
		panel.style.setProperty( '--bs-ui-panel-height', '0px' );
		panel.classList.add( 'bs-ui-open' );
		requestAnimationFrame( () => {
			panel.style.setProperty( '--bs-ui-panel-height', panel.scrollHeight + 'px' );
		} );
	} else {
		panel.style.setProperty( '--bs-ui-panel-height', panel.scrollHeight + 'px' );
		panel._onTransitionEnd = () => {
			panel.classList.remove( 'bs-ui-open' );
			panel._onTransitionEnd = null;
		};
		panel.addEventListener( 'transitionend', panel._onTransitionEnd, { once: true } );
		requestAnimationFrame( () => {
			panel.style.setProperty( '--bs-ui-panel-height', '0px' );
		} );
	}
}

store( 'bsui/accordion', {
	state: {
		get isOpen() {
			const { value, itemValue } = getContext();
			return Array.isArray( value ) && value.includes( itemValue );
		},
		get ariaExpanded() {
			const ctx = getContext();
			const isOpen =
				Array.isArray( ctx.value ) &&
				ctx.value.includes( ctx.itemValue );
			return isOpen ? 'true' : 'false';
		},
	},
	actions: {
		toggle() {
			const ctx = getContext();
			if ( ctx.disabled ) return;

			const { ref } = getElement();
			const item = ref.closest( '[data-bsui-accordion-item]' ) || ref.parentElement;
			const { value, multiple, itemValue } = ctx;
			const isOpen = value.includes( itemValue );

			if ( ! multiple ) {
				const root = ref.closest( '[data-bsui-accordion-root]' );
				if ( root ) {
					root.querySelectorAll( '[role="region"].bs-ui-open' ).forEach( ( p ) => {
						if ( p !== item?.querySelector( '[role="region"]' ) ) {
							animatePanel( p, false );
						}
					} );
				}
				ctx.value = isOpen ? [] : [ itemValue ];
			} else if ( isOpen ) {
				ctx.value = value.filter( ( v ) => v !== itemValue );
			} else {
				ctx.value = [ ...value, itemValue ];
			}

			const panel = item?.querySelector( '[role="region"]' );
			animatePanel( panel, ! isOpen );
		},
		handleKeyDown( event ) {
			const ctx = getContext();
			if ( ctx.disabled ) return;

			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-accordion-root]' );
			if ( ! root ) return;

			const triggers = [
				...root.querySelectorAll(
					'[data-bsui-accordion-trigger]:not([disabled])'
				),
			];
			const current = triggers.indexOf( ref );
			if ( current === -1 ) return;

			const vertical = ctx.orientation === 'vertical';
			const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
			const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';
			const loop = ctx.loop !== false;

			let next = -1;
			switch ( event.key ) {
				case nextKey:
					event.preventDefault();
					if ( current < triggers.length - 1 ) {
						next = current + 1;
					} else if ( loop ) {
						next = 0;
					}
					break;
				case prevKey:
					event.preventDefault();
					if ( current > 0 ) {
						next = current - 1;
					} else if ( loop ) {
						next = triggers.length - 1;
					}
					break;
				case 'Home':
					event.preventDefault();
					next = 0;
					break;
				case 'End':
					event.preventDefault();
					next = triggers.length - 1;
					break;
			}

			if ( next >= 0 ) {
				triggers[ next ].focus();
			}
		},
	},
	callbacks: {
		initPanel() {
			const { ref } = getElement();
			if ( ref.classList.contains( 'bs-ui-open' ) ) {
				ref.style.setProperty( '--bs-ui-panel-height', ref.scrollHeight + 'px' );
			}
		},
	},
} );
