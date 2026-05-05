<?php
$dismissable = $a['dismissable'] ?? true;
?>
<div
	data-wp-interactive="bsui/dialog"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'        => false,
		'dismissable' => (bool) $dismissable,
	) ) ); ?>'
	data-bsui-dialog-root

>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/dialog-trigger', 'bsui/dialog-popup', 'bsui/dialog-backdrop' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/dialog-trigger', array() ),
			array( 'bsui/dialog-backdrop', array() ),
			array( 'bsui/dialog-popup', array() ),
		) ) ); ?>'
	/>
</div>
