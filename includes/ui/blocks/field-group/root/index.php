<?php
$variant  = ! empty( $a['variant'] ) ? $a['variant'] : 'default';
$disabled = ! empty( $a['disabled'] );
?>
<fieldset
	data-bsui-field-group
	data-variant="<?php echo esc_attr( $variant ); ?>"
	<?php if ( $disabled ) echo 'disabled'; ?>
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/field-group-title', 'bsui/field-group-description', 'bsui/field', 'bsui/input', 'bsui/textarea', 'bsui/number-field', 'bsui/select', 'bsui/checkbox', 'bsui/switch', 'bsui/radio-group', 'bsui/stack', 'bsui/text', 'bsui/separator' ) ) ); ?>'
	/>
</fieldset>
