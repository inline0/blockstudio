( function ( blocks, element ) {
	blocks.registerBlockType( 'blockstudio-test/native-wp-block', {
		edit: function () {
			return element.createElement(
				'div',
				Object.assign( {}, ( blocks.useBlockProps ? blocks.useBlockProps() : {} ) ),
				element.createElement( 'p', null, 'Native WP Block (editor)' )
			);
		},
	} );
} )( window.wp.blocks, window.wp.element );
