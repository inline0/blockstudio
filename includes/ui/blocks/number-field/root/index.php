<?php
$value    = $a['defaultValue'] ?? '';
$min      = $a['min'] ?? '';
$max      = $a['max'] ?? '';
$step     = $a['step'] ?? 1;
$name     = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$disabled  = ! empty( $a['disabled'] );
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<div
	data-wp-interactive="bsui/number-field"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'value'    => $value !== '' ? (float) $value : null,
		'min'      => $min !== '' ? (float) $min : null,
		'max'      => $max !== '' ? (float) $max : null,
		'step'     => (float) $step,
		'disabled' => $disabled,
	) ) ); ?>'
	role="group"
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<button data-bsui-focus data-wp-on--click="actions.decrement" aria-label="Decrease" <?php if ( $disabled ) echo 'disabled'; ?>>-</button>
	<input
		data-bsui-focus
		type="text"
		inputmode="numeric"
		data-wp-bind--value="state.displayValue"
		data-wp-on--change="actions.handleInput"
		data-wp-on--keydown="actions.handleKeyDown"
		<?php if ( '' !== $name ) : ?>name="<?php echo esc_attr( $name ); ?>"<?php endif; ?>
		<?php if ( $disabled ) echo 'disabled'; ?>
		value="<?php echo esc_attr( $value ); ?>"
		role="spinbutton"
		aria-label="Value"
		aria-valuenow="<?php echo esc_attr( $value ); ?>"
		data-wp-bind--aria-valuenow="state.displayValue"
		<?php if ( '' !== $min ) : ?>aria-valuemin="<?php echo esc_attr( $min ); ?>"<?php endif; ?>
		<?php if ( '' !== $max ) : ?>aria-valuemax="<?php echo esc_attr( $max ); ?>"<?php endif; ?>
		data-wp-bind--aria-valuemin="context.min"
		data-wp-bind--aria-valuemax="context.max"
	/>
	<button data-bsui-focus data-wp-on--click="actions.increment" aria-label="Increase" <?php if ( $disabled ) echo 'disabled'; ?>>+</button>
</div>
