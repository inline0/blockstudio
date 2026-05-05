document.querySelectorAll( '[data-bsui-field-root]' ).forEach( ( field ) => {
	const label = field.querySelector( 'label' );
	const input = field.querySelector( 'input:not([type="hidden"]), select, textarea' );
	if ( label && input ) {
		if ( ! input.id ) {
			input.id = 'bsui-field-' + Math.random().toString( 36 ).slice( 2, 9 );
		}
		label.setAttribute( 'for', input.id );
	}
} );
