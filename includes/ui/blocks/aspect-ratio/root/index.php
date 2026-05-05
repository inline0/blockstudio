<?php
$ratio = ! empty( $a['ratio'] ) ? $a['ratio'] : '16/9';
$bg    = ! empty( $a['background'] ) ? 'background:' . $a['background'] . ';' : '';
?>
<div
	data-bsui-aspect-ratio
	style="position: relative; width: 100%; aspect-ratio: <?php echo esc_attr( $ratio ); ?>; overflow: hidden; border-radius: var(--bs-ui-radius-lg); <?php echo esc_attr( $bg ); ?>"
>
	<InnerBlocks />
</div>
