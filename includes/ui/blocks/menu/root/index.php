<?php
$menu_id = wp_unique_id( 'bs-ui-menu-' );
?>
<div
	data-wp-interactive="bsui/menu"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'        => false,
		'activeIndex' => -1,
		'triggerId'   => $menu_id . '-trigger',
		'popupId'     => $menu_id . '-popup',
	) ) ); ?>'
	data-bsui-menu-root
	data-wp-on-document--click="actions.handleOutsideClick"
	data-wp-on-document--keydown="actions.handleDocumentKeyDown"
	style="display:inline-block;position:relative"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/menu-trigger', 'bsui/menu-popup' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/menu-trigger', array() ),
			array( 'bsui/menu-popup', array() ),
		) ) ); ?>'
	/>
</div>
