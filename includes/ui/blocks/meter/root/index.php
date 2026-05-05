<?php
$value   = $a['value'] ?? 0;
$min     = $a['min'] ?? 0;
$max     = $a['max'] ?? 100;
$label   = ! empty( $a['label'] ) ? $a['label'] : '';
$percent = $max > $min ? ( ( $value - $min ) / ( $max - $min ) ) * 100 : 0;
?>
<div
	role="meter"
	aria-valuenow="<?php echo esc_attr( $value ); ?>"
	aria-valuemin="<?php echo esc_attr( $min ); ?>"
	aria-valuemax="<?php echo esc_attr( $max ); ?>"
	<?php if ( '' !== $label ) : ?>
	aria-label="<?php echo esc_attr( $label ); ?>"
	<?php endif; ?>
	style="--meter-value: <?php echo esc_attr( $percent ); ?>%"
>
	<InnerBlocks />
</div>
