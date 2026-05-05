import { store, getElement } from '@wordpress/interactivity';

store( 'bsui/carousel', {
	actions: {
		updateFade() {
			const { ref } = getElement();
			const viewport = ref.closest( '[data-bsui-carousel-viewport]' ) || ref.querySelector( '[data-bsui-carousel-viewport]' );
			const carousel = ref.closest( '[data-bsui-carousel]' ) || ref;
			if ( ! viewport ) return;

			const left = viewport.scrollLeft > 2;
			const right = viewport.scrollLeft < viewport.scrollWidth - viewport.clientWidth - 2;

			if ( left && right ) carousel.setAttribute( 'data-fade', 'both' );
			else if ( right ) carousel.setAttribute( 'data-fade', 'right' );
			else if ( left ) carousel.setAttribute( 'data-fade', 'left' );
			else carousel.removeAttribute( 'data-fade' );
		},
	},
} );
