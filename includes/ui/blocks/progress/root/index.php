<?php
$value   = $a['value'] ?? 0;
$max     = $a['max'] ?? 100;
$label   = ! empty( $a['label'] ) ? $a['label'] : '';
$percent = $max > 0 ? ( $value / $max ) * 100 : 0;
?>
<div
	role="progressbar"
	aria-valuenow="<?php echo esc_attr( $value ); ?>"
	aria-valuemin="0"
	aria-valuemax="<?php echo esc_attr( $max ); ?>"
	<?php if ( '' !== $label ) : ?>
	aria-label="<?php echo esc_attr( $label ); ?>"
	<?php endif; ?>
	style="--progress-value: <?php echo esc_attr( $percent ); ?>%"
>
	<InnerBlocks />
</div>
