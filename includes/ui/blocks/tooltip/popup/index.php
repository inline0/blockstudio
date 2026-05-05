<?php
$popup_id = $context['bsui/tooltip']['popupId'] ?? '';
?>
<div
	data-wp-interactive="bsui/tooltip"

	data-wp-on--mouseenter="actions.handleMouseEnter"
	data-wp-on--mouseleave="actions.handleMouseLeave"
	role="tooltip"
	id="<?php echo esc_attr( $popup_id ); ?>"
	hidden
>
	<RichText attribute="content" tag="span" placeholder="Tooltip text" />
</div>
