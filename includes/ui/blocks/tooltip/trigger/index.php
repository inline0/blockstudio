<?php
$popup_id = $context['bsui/tooltip']['popupId'] ?? '';
?>
<div
	data-wp-interactive="bsui/tooltip"
	data-wp-on--mouseenter="actions.handleMouseEnter"
	data-wp-on--mouseleave="actions.handleMouseLeave"
	data-wp-on--focusin="actions.handleFocusIn"
	data-wp-on--focusout="actions.handleFocusOut"
	data-wp-on--keydown="actions.handleKeyDown"
	data-bsui-tooltip-trigger
	aria-describedby="<?php echo esc_attr( $popup_id ); ?>"
	style="display:inline"
>
	<?php echo $a['trigger'] ?? ''; ?>
</div>
