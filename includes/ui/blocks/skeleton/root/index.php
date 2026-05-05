<?php
$width   = ! empty( $a['width'] ) ? 'width:' . $a['width'] . ';' : '';
$height  = ! empty( $a['height'] ) ? $a['height'] : '1rem';
$rounded = ! empty( $a['rounded'] );
$radius  = $rounded ? 'var(--bs-ui-radius-full)' : 'var(--bs-ui-radius)';
?>
<div
	data-bsui-skeleton
	style="height: <?php echo esc_attr( $height ); ?>; <?php echo esc_attr( $width ); ?> border-radius: <?php echo esc_attr( $radius ); ?>;"
></div>
