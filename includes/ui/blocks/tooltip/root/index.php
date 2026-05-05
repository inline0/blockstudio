<?php
$open_delay  = $a['openDelay'] ?? 600;
$close_delay = $a['closeDelay'] ?? 0;
$popup_id    = wp_unique_id( 'tooltip-popup-' );
?>
<div
	data-wp-interactive="bsui/tooltip"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'       => false,
		'openDelay'  => (int) $open_delay,
		'closeDelay' => (int) $close_delay,
		'popupId'    => $popup_id,
	) ) ); ?>'
	data-bsui-tooltip-root
	style="display:inline-block;position:relative"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/tooltip-trigger', 'bsui/tooltip-popup' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/tooltip-trigger', array() ),
			array( 'bsui/tooltip-popup', array() ),
		) ) ); ?>'
	/>
</div>
