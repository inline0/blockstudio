import { store, getContext, getElement } from '@wordpress/interactivity';

function updateStars( root, filled ) {
	root.querySelectorAll( '[data-star]' ).forEach( ( btn ) => {
		const star = parseInt( btn.dataset.star, 10 );
		if ( star <= filled ) {
			btn.setAttribute( 'data-filled', '' );
		} else {
			btn.removeAttribute( 'data-filled' );
		}
		btn.setAttribute( 'aria-checked', star <= filled ? 'true' : 'false' );
	} );
}

store( 'bsui/rating', {
	state: {
		get formValue() {
			return String( getContext().value );
		},
	},
	actions: {
		select() {
			const ctx = getContext();
			const { ref } = getElement();
			const star = parseInt( ref.dataset.star, 10 );
			ctx.value = ctx.value === star ? 0 : star;
			const root = ref.closest( '[data-bsui-rating]' );
			updateStars( root, ctx.value );
			root.dispatchEvent( new CustomEvent( 'change', { bubbles: true, detail: { value: ctx.value } } ) );
		},
		hoverIn() {
			const ctx = getContext();
			const { ref } = getElement();
			const star = parseInt( ref.dataset.star, 10 );
			ctx.hover = star;
			const root = ref.closest( '[data-bsui-rating]' );
			updateStars( root, star );
		},
		hoverOut() {
			const ctx = getContext();
			ctx.hover = 0;
			const { ref } = getElement();
			const root = ref.closest( '[data-bsui-rating]' );
			updateStars( root, ctx.value );
		},
	},
} );

// Init stars on load.
setTimeout( () => {
	document.querySelectorAll( '[data-bsui-rating]' ).forEach( ( root ) => {
		const value = parseInt( root.querySelector( 'input[type="hidden"]' )?.value || '0', 10 );
		updateStars( root, value );
	} );
}, 0 );
