<?php
$orientation = $a['orientation'] ?? 'vertical';
$overflow    = 'both' === $orientation ? 'auto' : ( 'horizontal' === $orientation ? 'auto hidden' : 'hidden auto' );
$max_height  = (string) ( $a['maxHeight'] ?? '' );
$max_height  = preg_match( '/^\d+(\.\d+)?(px|rem|em|vh|%)$/', $max_height ) ? $max_height : '15rem';
$sizing      = 'horizontal' === $orientation ? '' : ' max-height: ' . $max_height . ';';
?>
<div
	data-bsui-focus
	data-bsui-scroll-area
	data-orientation="<?php echo esc_attr( $orientation ); ?>"
	tabindex="0"
	role="region"
	aria-label="Scrollable content"
	style="overflow: <?php echo esc_attr( $overflow ); ?>;<?php echo esc_attr( $sizing ); ?>"
>
	<InnerBlocks />
</div>
