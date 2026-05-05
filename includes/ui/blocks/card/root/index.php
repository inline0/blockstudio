<?php
?>
<div data-bsui-card>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
		'bsui/card-header',
		'bsui/card-content',
		'bsui/card-footer',
	) ) ); ?>' />
</div>
