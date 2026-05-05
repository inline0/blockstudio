<?php
$value       = $a['defaultValue'] ?? 50;
$min         = $a['min'] ?? 0;
$max         = $a['max'] ?? 100;
$step        = $a['step'] ?? 1;
$disabled    = ! empty( $a['disabled'] );
$name        = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$orientation = $a['orientation'] ?? 'horizontal';
$label       = ! empty( $a['label'] ) ? $a['label'] : '';
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$percent     = $max > $min ? ( ( $value - $min ) / ( $max - $min ) ) * 100 : 0;
?>
<div
	data-wp-interactive="bsui/slider"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'value'       => (float) $value,
		'min'         => (float) $min,
		'max'         => (float) $max,
		'step'        => (float) $step,
		'disabled'    => $disabled,
		'orientation' => $orientation,
	) ) ); ?>'
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<div
		data-bsui-focus
		data-wp-watch="callbacks.syncPercent"
		data-wp-on--pointerdown="actions.handlePointerDown"
		data-wp-on--keydown="actions.handleKeyDown"
		role="slider"
		tabindex="0"
		aria-valuemin="<?php echo esc_attr( $min ); ?>"
		aria-valuemax="<?php echo esc_attr( $max ); ?>"
		aria-valuenow="<?php echo esc_attr( $value ); ?>"
		aria-valuetext="<?php echo esc_attr( $value ); ?>"
		aria-orientation="<?php echo esc_attr( $orientation ); ?>"
		<?php if ( '' !== $label ) : ?>
		aria-label="<?php echo esc_attr( $label ); ?>"
		<?php endif; ?>
		data-wp-bind--aria-valuenow="state.currentValue"
		data-wp-bind--aria-valuetext="state.valueText"
		style="--bs-ui-slider-percent: <?php echo esc_attr( $percent ); ?>%"
		<?php if ( $disabled ) echo 'aria-disabled="true"'; ?>
	>
		<InnerBlocks />
	</div>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		data-wp-bind--value="state.currentValue"
		value="<?php echo esc_attr( $value ); ?>"
	/>
	<?php endif; ?>
</div>
