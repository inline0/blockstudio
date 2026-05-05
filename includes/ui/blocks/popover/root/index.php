<?php
$open = ! empty( $a['defaultOpen'] );
?>
<div
	data-wp-interactive="bsui/popover"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open' => $open,
	) ) ); ?>'
	data-bsui-popover-root
	data-wp-on-document--click="actions.handleOutsideClick"
	data-wp-on-document--keydown="actions.handleEscape"
	style="display:inline-block;position:relative"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/popover-trigger', 'bsui/popover-popup', 'bsui/popover-close' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/popover-trigger', array() ),
			array( 'bsui/popover-popup', array() ),
		) ) ); ?>'
	/>
</div>
