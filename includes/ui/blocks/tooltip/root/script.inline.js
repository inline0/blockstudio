import { computePosition, flip, shift, offset } from 'https://esm.sh/@floating-ui/dom@1.7.4';
import { store, getContext, getElement } from '@wordpress/interactivity';

const timers = new WeakMap();

function getTimers( ref ) {
	if ( ! timers.has( ref ) ) {
		timers.set( ref, { open: null, close: null } );
	}
	return timers.get( ref );
}

function positionTooltip( root ) {
	const trigger = root.querySelector( 'button, [data-wp-on--mouseenter]' );
	const popup = root.querySelector( '[role="tooltip"]' );
	if ( ! trigger || ! popup ) return;

	computePosition( window.__bsui.getAnchor( trigger ), popup, {
		placement: 'top',
		middleware: [ offset( 8 ), flip(), shift( { padding: 8 } ) ],
	} ).then( ( { x, y } ) => {
		Object.assign( popup.style, { left: x + 'px', top: y + 'px' } );
	} );
}

function openTooltip( ctx, root ) {
	ctx.open = true;
	const popup = root?.querySelector( '[role="tooltip"]' );
	if ( popup ) popup.removeAttribute( 'hidden' );
	requestAnimationFrame( () => {
		if ( root ) positionTooltip( root );
	} );
}

function closeTooltip( ctx, root ) {
	ctx.open = false;
	const popup = root?.querySelector( '[role="tooltip"]' );
	if ( popup ) popup.setAttribute( 'hidden', '' );
}

store( 'bsui/tooltip', {
	actions: {
		handleMouseEnter() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-tooltip-root]' );
			const t = getTimers( ctx );
			clearTimeout( t.close );

			if ( ctx.openDelay > 0 ) {
				t.open = setTimeout( () => openTooltip( ctx, root ), ctx.openDelay );
			} else {
				openTooltip( ctx, root );
			}
		},
		handleMouseLeave() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-tooltip-root]' );
			const t = getTimers( ctx );
			clearTimeout( t.open );

			if ( ctx.closeDelay > 0 ) {
				t.close = setTimeout( () => closeTooltip( ctx, root ), ctx.closeDelay );
			} else {
				closeTooltip( ctx, root );
			}
		},
		handleFocusIn() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-tooltip-root]' );
			const t = getTimers( ctx );
			clearTimeout( t.close );
			openTooltip( ctx, root );
		},
		handleFocusOut() {
			const ctx = getContext();
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-tooltip-root]' );
			const t = getTimers( ctx );
			clearTimeout( t.open );
			closeTooltip( ctx, root );
		},
		handleKeyDown( event ) {
			if ( event.key === 'Escape' ) {
				const ctx = getContext();
				const { ref } = getElement();
				const root = ref.closest( '[data-bsui-tooltip-root]' );
				closeTooltip( ctx, root );
			}
		},
	},
} );
