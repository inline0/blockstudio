<?php
$open = ! empty( $context['bsui/collapsible']['defaultOpen'] ?? false );
?>
<div
	data-wp-interactive="bsui/collapsible"
	data-wp-bind--hidden="state.panelHidden"
	<?php if ( ! $open ) echo 'hidden'; ?>
>
	<InnerBlocks />
</div>
