<?php
$default_value = $a['defaultValue'] ?? '';
$multiple      = ! empty( $a['multiple'] );
$orientation   = $a['orientation'] ?? 'vertical';
$disabled      = ! empty( $a['disabled'] );
$loop          = $a['loop'] ?? true;

$value = array();
if ( '' !== $default_value ) {
	$value[] = $default_value;
}
?>
<div
	data-wp-interactive="bsui/accordion"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'value'       => $value,
		'multiple'    => $multiple,
		'orientation' => $orientation,
		'disabled'    => $disabled,
		'loop'        => (bool) $loop,
	) ) ); ?>'
	data-bsui-accordion-root
	data-orientation="<?php echo esc_attr( $orientation ); ?>"
	<?php if ( $disabled ) echo 'data-disabled'; ?>
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/accordion-item' ) ) ); ?>'
	/>
</div>
