<?php
$default_value = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '';
$multiple      = ! empty( $a['multiple'] );
$orientation   = $a['orientation'] ?? 'horizontal';
$value         = array();
if ( '' !== $default_value ) {
	$value[] = $default_value;
}
?>
<div
	data-wp-interactive="bsui/toggle-group"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'value'       => $value,
		'multiple'    => $multiple,
		'orientation' => $orientation,
	) ) ); ?>'
	data-bsui-toggle-group-root
	role="group"
	aria-orientation="<?php echo esc_attr( $orientation ); ?>"
	data-wp-on--keydown="actions.handleKeyDown"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/toggle-group-item' ) ) ); ?>'
	/>
</div>
