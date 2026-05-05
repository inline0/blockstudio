<div
	data-wp-interactive="bsui/menu"
	data-wp-on--click="actions.toggle"
	data-wp-on--keydown="actions.handleTriggerKeyDown"
	data-wp-bind--id="context.triggerId"
	data-wp-bind--aria-expanded="state.ariaExpanded"
	data-wp-bind--aria-controls="context.popupId"
	data-bsui-menu-trigger
	aria-haspopup="menu"
	aria-expanded="false"
	style="display:inline"
>
	<?php echo $a['trigger'] ?? ''; ?>
</div>
