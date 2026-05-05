<div
	data-bsui-focus
	data-wp-interactive="bsui/context-menu"

	data-wp-on--keydown="actions.handlePopupKeyDown"
	role="menu"
	tabindex="-1"
	hidden
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/context-menu-item' ) ) ); ?>' />
</div>
