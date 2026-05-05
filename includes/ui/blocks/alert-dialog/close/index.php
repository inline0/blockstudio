<?php
$is_x = ! empty( $a['x'] );
?>
<div data-wp-interactive="bsui/alert-dialog" data-wp-on--click="actions.close" <?php if ( $is_x ) echo 'data-bsui-overlay-x'; ?>>
	<?php
	echo bs_block( array(
		'name' => 'bsui/button',
		'data' => $is_x
			? array( 'variant' => 'ghost', 'size' => 'icon', 'label' => '✕' )
			: array( 'variant' => 'outline', 'label' => $a['label'] ?? 'Close' ),
	) );
	?>
</div>
