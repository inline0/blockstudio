<div useBlockProps>
	<InnerBlocks template="<?php echo esc_attr( wp_json_encode( array(
		array( 'core/heading', array( 'placeholder' => 'Heading' ) ),
		array( 'core/group', array( 'layout' => array( 'type' => 'flex' ) ), array(
			array( 'core/paragraph', array( 'placeholder' => 'Nested paragraph' ) ),
		) ),
	) ) ); ?>" />
</div>
