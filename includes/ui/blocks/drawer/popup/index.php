<?php $flush = ! empty( $a['flush'] ); ?>
<div
	data-bsui-focus
	data-wp-interactive="bsui/drawer"
	data-wp-on--keydown="actions.handleFocusTrap"
	role="dialog"
	aria-modal="true"
	tabindex="-1"
	hidden
	<?php if ( $flush ) : ?>data-flush<?php endif; ?>
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/drawer-close', 'core/heading', 'core/paragraph', 'core/group' ) ) ); ?>' />
</div>
