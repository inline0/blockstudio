<?php
$name      = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$default   = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '';
$step      = ! empty( $a['step'] ) ? (int) $a['step'] : 1;
$disabled  = ! empty( $a['disabled'] );
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';

$hour   = '';
$minute = '';
if ( $default ) {
	$parts  = explode( ':', $default );
	$hour   = str_pad( (int) ( $parts[0] ?? 0 ), 2, '0', STR_PAD_LEFT );
	$minute = str_pad( (int) ( $parts[1] ?? 0 ), 2, '0', STR_PAD_LEFT );
}
?>
<div
	data-bsui-time-picker
	data-wp-interactive="bsui/time-picker"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'hour'     => $hour !== '' ? (int) $hour : null,
		'minute'   => $minute !== '' ? (int) $minute : null,
		'step'     => $step,
		'disabled' => $disabled,
	) ) ); ?>'
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<input
		data-bsui-focus
		data-bsui-time-hour
		type="text"
		inputmode="numeric"
		maxlength="2"
		placeholder="HH"
		value="<?php echo esc_attr( $hour ); ?>"
		data-wp-on--input="actions.handleHourInput"
		data-wp-on--keydown="actions.handleHourKeydown"
		data-wp-on--focus="actions.selectAll"
		data-wp-on--blur="actions.padHour"
		aria-label="Hours"
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
	<span data-bsui-time-separator>:</span>
	<input
		data-bsui-focus
		data-bsui-time-minute
		type="text"
		inputmode="numeric"
		maxlength="2"
		placeholder="MM"
		value="<?php echo esc_attr( $minute ); ?>"
		data-wp-on--input="actions.handleMinuteInput"
		data-wp-on--keydown="actions.handleMinuteKeydown"
		data-wp-on--focus="actions.selectAll"
		data-wp-on--blur="actions.padMinute"
		aria-label="Minutes"
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
	<?php if ( '' !== $name ) : ?>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" data-wp-bind--value="state.formValue" />
	<?php endif; ?>
</div>
