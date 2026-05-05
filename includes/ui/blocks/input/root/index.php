<?php
$type        = $a['type'] ?? 'text';
$name        = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$placeholder = ! empty( $a['placeholder'] ) ? $a['placeholder'] : '';
$disabled    = ! empty( $a['disabled'] );
$required    = ! empty( $a['required'] );
$default     = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '';
$value       = ! empty( $a['value'] ) ? $a['value'] : '';
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$in_form     = ! empty( $context['bsui/form'] );
?>
<input
	data-bsui-focus
	type="<?php echo esc_attr( $type ); ?>"
	<?php if ( '' !== $name ) : ?>name="<?php echo esc_attr( $name ); ?>"<?php endif; ?>
	<?php if ( '' !== $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
	<?php if ( '' !== $default ) : ?>value="<?php echo esc_attr( $default ); ?>"<?php endif; ?>
	<?php if ( $disabled ) echo 'disabled'; ?>
	<?php if ( $required ) echo 'required aria-required="true"'; ?>
	<?php if ( $value ) echo 'data-wp-bind--value="' . esc_attr( $value ) . '"'; ?>
	<?php if ( $on_change ) echo 'data-wp-on--input="' . esc_attr( $on_change ) . '"'; ?>
	<?php if ( $in_form && ! $on_change ) echo 'data-wp-on--input="actions.clearFieldError"'; ?>
/>
