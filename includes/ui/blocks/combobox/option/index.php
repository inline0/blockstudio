<?php
$value = ! empty( $a['value'] ) ? $a['value'] : '';
$label = ! empty( $a['label'] ) ? $a['label'] : $value;
?>
<div
	data-wp-interactive="bsui/combobox"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'optionValue' => $value,
		'optionLabel' => $label,
	) ) ); ?>'
	data-wp-on--click="actions.selectOption"
	data-value="<?php echo esc_attr( $value ); ?>"
	role="option"
	tabindex="-1"
><?php echo esc_html( $label ); ?></div>
