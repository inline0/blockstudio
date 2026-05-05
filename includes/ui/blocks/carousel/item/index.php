<?php
$width = ! empty( $a['width'] ) ? $a['width'] : '16rem';
?>
<div data-bsui-carousel-item role="group" aria-roledescription="slide" style="width: <?php echo esc_attr( $width ); ?>;">
	<InnerBlocks />
</div>
