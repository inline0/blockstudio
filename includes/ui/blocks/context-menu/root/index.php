<div
	data-wp-interactive="bsui/context-menu"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'        => false,
		'activeIndex' => -1,
		'x'           => 0,
		'y'           => 0,
	) ) ); ?>'
	data-bsui-context-menu-root
	data-wp-on-document--click="actions.handleOutsideClick"
	data-wp-on-document--keydown="actions.handleDocumentKeyDown"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/context-menu-trigger', 'bsui/context-menu-popup' ) ) ); ?>' />
</div>
