<?php
?>
<li
	data-wp-interactive="bsui/navigation-menu"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'open' => false ) ) ); ?>'
	data-bsui-nav-menu-item
	data-wp-on--mouseenter="actions.open"
	data-wp-on--mouseleave="actions.close"
	data-wp-on-document--click="actions.handleOutsideClick"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
		'bsui/navigation-menu-trigger',
		'bsui/navigation-menu-content',
	) ) ); ?>' />
</li>
