<?php
?>
<div
	data-wp-interactive="bsui/menubar"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'subOpen' => false ) ) ); ?>'
	data-bsui-menubar-submenu
	data-wp-on--mouseenter="actions.openSub"
	data-wp-on--mouseleave="actions.closeSub"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
		'bsui/menubar-submenu-trigger',
		'bsui/menubar-submenu-popup',
	) ) ); ?>' />
</div>
