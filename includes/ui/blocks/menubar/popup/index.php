<?php
?>
<div
	data-wp-interactive="bsui/menubar"

	data-bsui-menubar-popup
	role="menu"
	hidden
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/menubar-item', 'bsui/menubar-submenu' ) ) ); ?>' />
</div>
