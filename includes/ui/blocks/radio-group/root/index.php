<?php
$default     = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '';
$name        = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$disabled    = ! empty( $a['disabled'] );
$orientation = $a['orientation'] ?? 'vertical';
$value       = ! empty( $a['value'] ) ? $a['value'] : '';
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<div
	data-wp-interactive="bsui/radio-group"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'value'       => $default,
		'disabled'    => $disabled,
		'orientation' => $orientation,
		'name'        => $name,
	) ) ); ?>'
	data-bsui-radio-group-root
	role="radiogroup"
	aria-orientation="<?php echo esc_attr( $orientation ); ?>"
	data-wp-on--keydown="actions.handleKeyDown"
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/radio' ) ) ); ?>'
	/>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		data-wp-bind--value="state.selectedValue"
		value="<?php echo esc_attr( $default ); ?>"
	/>
	<?php endif; ?>
</div>
