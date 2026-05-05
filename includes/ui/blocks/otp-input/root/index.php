<?php
$name     = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$length   = ! empty( $a['length'] ) ? (int) $a['length'] : 6;
$disabled  = ! empty( $a['disabled'] );
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<div
	data-wp-interactive="bsui/otp-input"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'length' => $length ) ) ); ?>'
	data-bsui-otp-input
	role="group"
	aria-label="Verification code"
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<?php for ( $i = 0; $i < $length; $i++ ) : ?>
	<input
		data-bsui-focus
		type="text"
		inputmode="numeric"
		maxlength="1"
		pattern="[0-9]"
		autocomplete="one-time-code"
		data-index="<?php echo $i; ?>"
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
	<?php if ( $i === (int) floor( $length / 2 ) - 1 && $length > 3 ) : ?>
	<span data-bsui-otp-separator>-</span>
	<?php endif; ?>
	<?php endfor; ?>
	<?php if ( '' !== $name ) : ?>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" />
	<?php endif; ?>
</div>
