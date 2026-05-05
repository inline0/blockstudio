<?php
$default_value    = $a['defaultValue'] ?? '';
$orientation      = $a['orientation'] ?? 'horizontal';
$activate_on_focus = $a['activateOnFocus'] ?? true;
$loop             = $a['loop'] ?? true;
?>
<div
	data-wp-interactive="bsui/tabs"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'activeValue'     => $default_value,
		'orientation'     => $orientation,
		'activateOnFocus' => (bool) $activate_on_focus,
		'loop'            => (bool) $loop,
	) ) ); ?>'
	data-wp-init="callbacks.init"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/tabs-list', 'bsui/tabs-panel' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/tabs-list', array() ),
			array( 'bsui/tabs-panel', array() ),
		) ) ); ?>'
	/>
</div>
