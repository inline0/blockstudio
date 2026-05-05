<?php
$orientation = $a['orientation'] ?? 'vertical';
$overflow    = 'both' === $orientation ? 'auto' : ( 'horizontal' === $orientation ? 'auto hidden' : 'hidden auto' );
?>
<div
	data-bsui-focus
	data-bsui-scroll-area
	data-orientation="<?php echo esc_attr( $orientation ); ?>"
	tabindex="0"
	role="region"
	aria-label="Scrollable content"
	style="overflow: <?php echo esc_attr( $overflow ); ?>"
>
	<InnerBlocks />
</div>
