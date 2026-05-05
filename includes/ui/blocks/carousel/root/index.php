<?php
$orientation = $a['orientation'] ?? 'horizontal';
?>
<div data-wp-interactive="bsui/carousel" data-bsui-carousel data-fade="right" data-orientation="<?php echo esc_attr( $orientation ); ?>" role="region" aria-roledescription="carousel">
	<div data-bsui-carousel-viewport data-wp-on--scroll="actions.updateFade" data-wp-init="actions.updateFade">
		<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/carousel-item' ) ) ); ?>' />
	</div>
</div>
