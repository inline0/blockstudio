<?php
$value         = $a['value'] ?? '';
$label         = $a['label'] ?? '';
$disabled      = ! empty( $a['disabled'] );
$default_value = $context['bsui/select']['defaultValue'] ?? '';
$is_selected   = $value !== '' && $value === $default_value;
$option_id     = wp_unique_id( 'select-option-' );
?>
<div
	data-wp-interactive="bsui/select"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'optionValue' => $value,
		'optionLabel' => $label,
	) ) ); ?>'
	data-wp-on--click="actions.selectOption"
	data-wp-bind--aria-selected="state.ariaSelected"
	data-value="<?php echo esc_attr( $value ); ?>"
	id="<?php echo esc_attr( $option_id ); ?>"
	role="option"
	tabindex="-1"
	aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
	<?php if ( $disabled ) echo 'aria-disabled="true"'; ?>
>
	<?php echo esc_html( $label ); ?>
</div>
