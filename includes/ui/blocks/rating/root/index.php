<?php
$name     = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$max      = ! empty( $a['max'] ) ? (int) $a['max'] : 5;
$default  = ! empty( $a['defaultValue'] ) ? (int) $a['defaultValue'] : 0;
$disabled  = ! empty( $a['disabled'] );
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
?>
<div
	data-wp-interactive="bsui/rating"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'value' => $default,
		'hover' => 0,
		'max'   => $max,
	) ) ); ?>'
	data-bsui-rating
	role="radiogroup"
	aria-label="Rating"
	<?php if ( $disabled ) echo 'data-disabled'; ?>
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<?php for ( $i = 1; $i <= $max; $i++ ) : ?>
	<button
		data-bsui-focus
		data-wp-interactive="bsui/rating"
		data-wp-on--click="actions.select"
		data-wp-on--mouseenter="actions.hoverIn"
		data-wp-on--mouseleave="actions.hoverOut"
		data-star="<?php echo $i; ?>"
		type="button"
		role="radio"
		aria-checked="<?php echo $i <= $default ? 'true' : 'false'; ?>"
		aria-label="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>"
		<?php if ( $disabled ) echo 'disabled'; ?>
	></button>
	<?php endfor; ?>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		value="<?php echo esc_attr( $default ); ?>"
		data-wp-bind--value="state.formValue"
	/>
	<?php endif; ?>
</div>
