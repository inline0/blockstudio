<?php
$open = ! empty( $context['bsui/popover']['defaultOpen'] ?? false );
?>
<div
	data-wp-interactive="bsui/popover"
	data-wp-on--click="actions.toggle"
	data-wp-bind--aria-expanded="state.ariaExpanded"
	data-bsui-popover-trigger
	aria-haspopup="dialog"
	aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
	style="display:inline"
>
	<?php echo $a['trigger'] ?? ''; ?>
</div>
