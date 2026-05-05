<?php
$name        = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$placeholder = ! empty( $a['placeholder'] ) ? $a['placeholder'] : 'Enter password';
$disabled    = ! empty( $a['disabled'] );
$value       = ! empty( $a['value'] ) ? $a['value'] : '';
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<div
	data-bsui-password-input
	data-wp-interactive="bsui/password-input"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'visible' => false ) ) ); ?>'
>
	<input
		data-bsui-focus
		data-wp-bind--type="state.inputType"
		type="password"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		<?php if ( '' !== $name ) echo 'name="' . esc_attr( $name ) . '"'; ?>
		<?php if ( $value ) echo 'data-wp-bind--value="' . esc_attr( $value ) . '"'; ?>
		<?php if ( $on_change ) echo 'data-wp-on--input="' . esc_attr( $on_change ) . '"'; ?>
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
	<button type="button" aria-label="Toggle password visibility" data-wp-on--click="actions.toggle">
		<span data-bsui-password-icon></span>
	</button>
</div>
