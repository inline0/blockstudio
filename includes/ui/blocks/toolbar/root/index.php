<?php
$orientation = $a['orientation'] ?? 'horizontal';
?>
<div
	data-wp-interactive="bsui/toolbar"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'orientation' => $orientation ) ) ); ?>'
	data-bsui-toolbar-root
	data-wp-on--keydown="actions.handleKeyDown"
	role="toolbar"
	aria-orientation="<?php echo esc_attr( $orientation ); ?>"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
		'bsui/toolbar-button',
		'bsui/toolbar-separator',
		'bsui/toggle-group',
		'bsui/select',
		'bsui/separator',
	) ) ); ?>' />
</div>
