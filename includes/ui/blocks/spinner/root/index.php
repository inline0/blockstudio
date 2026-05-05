<?php
$size  = $a['size'] ?? 'default';
$label = ! empty( $a['label'] ) ? $a['label'] : 'Loading';
?>
<span data-bsui-spinner data-size="<?php echo esc_attr( $size ); ?>" role="status" aria-label="<?php echo esc_attr( $label ); ?>">
	<span class="sr-only"><?php echo esc_html( $label ); ?></span>
</span>
