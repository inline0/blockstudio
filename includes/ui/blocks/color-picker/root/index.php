<?php
$name     = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$default  = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '#000000';
$disabled  = ! empty( $a['disabled'] );
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<div
	data-wp-interactive="bsui/color-picker"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'value' => $default ) ) ); ?>'
	data-bsui-color-picker
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<div data-bsui-color-swatch style="background-color: <?php echo esc_attr( $default ); ?>;">
		<input
			type="color"
			data-wp-on--input="actions.handleColorInput"
			value="<?php echo esc_attr( $default ); ?>"
			<?php if ( $disabled ) echo 'disabled'; ?>
		/>
	</div>
	<input
		data-bsui-focus
		data-wp-on--input="actions.handleTextInput"
		data-wp-bind--value="context.value"
		type="text"
		value="<?php echo esc_attr( $default ); ?>"
		maxlength="7"
		spellcheck="false"
		<?php if ( '' !== $name ) ?>name="<?php echo esc_attr( $name ); ?>"<?php ; ?>
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
</div>
