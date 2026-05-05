<?php
$open = ! empty( $context['bsui/popover']['defaultOpen'] ?? false );
?>
<div
	data-bsui-focus
	data-wp-interactive="bsui/popover"

	data-wp-on--keydown="actions.handleFocusTrap"
	role="dialog"
	tabindex="-1"
	<?php if ( ! $open ) echo 'hidden'; ?>
>
	<InnerBlocks />
</div>
