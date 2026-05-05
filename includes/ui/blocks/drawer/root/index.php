<?php
$position    = $a['position'] ?? 'left';
$dismissable = $a['dismissable'] ?? true;
?>
<div
	data-wp-interactive="bsui/drawer"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'        => false,
		'dismissable' => (bool) $dismissable,
		'position'    => $position,
	) ) ); ?>'
	data-bsui-drawer-root
	data-position="<?php echo esc_attr( $position ); ?>"
	data-wp-on-document--keydown="actions.handleEscape"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/drawer-trigger', 'bsui/drawer-popup', 'bsui/drawer-backdrop' ) ) ); ?>' />
</div>
