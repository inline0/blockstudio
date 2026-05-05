<?php
$open_delay  = $a['openDelay'] ?? 600;
$close_delay = $a['closeDelay'] ?? 300;
?>
<div
	data-wp-interactive="bsui/preview-card"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'       => false,
		'openDelay'  => (int) $open_delay,
		'closeDelay' => (int) $close_delay,
	) ) ); ?>'
	data-bsui-preview-card-root
	style="display:inline-block;position:relative"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/preview-card-trigger', 'bsui/preview-card-popup' ) ) ); ?>' />
</div>
