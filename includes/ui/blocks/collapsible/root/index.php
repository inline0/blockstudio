<?php
$default_open = ! empty( $a['defaultOpen'] );
$disabled     = ! empty( $a['disabled'] );
?>
<div
	data-wp-interactive="bsui/collapsible"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'     => $default_open,
		'disabled' => $disabled,
	) ) ); ?>'
	data-bsui-collapsible-root
	<?php if ( $disabled ) echo 'data-disabled'; ?>
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/collapsible-trigger', 'bsui/collapsible-panel' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/collapsible-trigger', array() ),
			array( 'bsui/collapsible-panel', array() ),
		) ) ); ?>'
	/>
</div>
