<?php
?>
<div
	data-wp-interactive="bsui/menubar"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'open' => false ) ) ); ?>'
	data-bsui-menubar-menu
	data-wp-on-document--click="actions.handleOutsideClick"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
		'bsui/menubar-trigger',
		'bsui/menubar-popup',
	) ) ); ?>' />
</div>
