<?php
$orientation = $context['bsui/tabs']['orientation'] ?? 'horizontal';
?>
<div
	data-wp-interactive="bsui/tabs"
	role="tablist"
	aria-orientation="<?php echo esc_attr( $orientation ); ?>"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/tabs-trigger' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/tabs-trigger', array() ),
			array( 'bsui/tabs-trigger', array() ),
		) ) ); ?>'
	/>
</div>
