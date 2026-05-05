<div
	data-bsui-focus
	data-wp-interactive="bsui/menu"
	data-wp-bind--id="context.popupId"

	data-wp-bind--aria-labelledby="context.triggerId"
	data-wp-on--keydown="actions.handlePopupKeyDown"
	role="menu"
	tabindex="-1"
	hidden
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/menu-item' ) ) ); ?>'
	/>
</div>
