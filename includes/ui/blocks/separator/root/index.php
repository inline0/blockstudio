<?php
$orientation = $a['orientation'] ?? 'horizontal';
?>
<div
	role="separator"
	aria-orientation="<?php echo esc_attr( $orientation ); ?>"
	data-orientation="<?php echo esc_attr( $orientation ); ?>"
></div>
