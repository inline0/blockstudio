<?php
$position = $a['position'] ?? 'bottom-right';
?>
<div
	data-wp-interactive="bsui/toast"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'toasts' => array(), 'position' => $position ) ) ); ?>'
	data-bsui-toast-provider
	data-position="<?php echo esc_attr( $position ); ?>"
	role="region"
	aria-live="polite"
	aria-label="Notifications"
>
	<InnerBlocks />
</div>
