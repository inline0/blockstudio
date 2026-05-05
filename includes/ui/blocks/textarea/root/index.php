<?php
$placeholder = ! empty( $a['placeholder'] ) ? $a['placeholder'] : '';
$rows        = ! empty( $a['rows'] ) ? (int) $a['rows'] : 4;
$name        = ! empty( $a['name'] ) ? $a['name'] : '';
$disabled    = ! empty( $a['disabled'] );
$value       = ! empty( $a['value'] ) ? $a['value'] : '';
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<textarea
	data-bsui-focus
	data-bsui-textarea
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
	rows="<?php echo esc_attr( $rows ); ?>"
	<?php if ( '' !== $name ) echo 'name="' . esc_attr( $name ) . '"'; ?>
	<?php if ( $disabled ) echo 'disabled'; ?>
	<?php if ( $value ) echo 'data-wp-bind--value="' . esc_attr( $value ) . '"'; ?>
	<?php if ( $on_change ) echo 'data-wp-on--input="' . esc_attr( $on_change ) . '"'; ?>
></textarea>
