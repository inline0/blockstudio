<?php
$item_value    = $context['bsui/accordion-item']['value'] ?? '';
$root_value    = $context['bsui/accordion']['defaultValue'] ?? '';
$root_disabled = ! empty( $context['bsui/accordion']['disabled'] ?? false );
$item_disabled = ! empty( $context['bsui/accordion-item']['disabled'] ?? false );
$is_open       = $item_value !== '' && $item_value === $root_value;
$disabled      = $root_disabled || $item_disabled;
$trigger_id    = 'ui-accordion-trigger-' . sanitize_title( $item_value );
$panel_id      = 'ui-accordion-panel-' . sanitize_title( $item_value );
?>
<button
	data-bsui-focus
	data-wp-interactive="bsui/accordion"
	data-bsui-accordion-trigger
	data-wp-on--click="actions.toggle"
	data-wp-on--keydown="actions.handleKeyDown"
	data-wp-bind--aria-expanded="state.ariaExpanded"
	aria-controls="<?php echo esc_attr( $panel_id ); ?>"
	id="<?php echo esc_attr( $trigger_id ); ?>"
	aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
	<?php if ( $disabled ) echo 'disabled'; ?>
>
	<RichText attribute="title" tag="span" placeholder="Accordion item" />
</button>
