<?php
$item_value    = $context['bsui/accordion-item']['value'] ?? '';
$root_value    = $context['bsui/accordion']['defaultValue'] ?? '';
$is_open       = $item_value !== '' && $item_value === $root_value;
$trigger_id    = 'ui-accordion-trigger-' . sanitize_title( $item_value );
$panel_id      = 'ui-accordion-panel-' . sanitize_title( $item_value );
?>
<div
	data-wp-interactive="bsui/accordion"
	data-wp-init="callbacks.initPanel"
	id="<?php echo esc_attr( $panel_id ); ?>"
	aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
	role="region"
	class="<?php echo $is_open ? 'bs-ui-open' : ''; ?>"
>
	<InnerBlocks />
</div>
