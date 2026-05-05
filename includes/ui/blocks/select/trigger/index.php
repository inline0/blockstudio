<?php
$open       = ! empty( $context['bsui/select']['defaultOpen'] ?? false );
$listbox_id = $context['bsui/select']['listboxId'] ?? '';
?>
<button
	type="button"
	data-bsui-focus
	data-wp-interactive="bsui/select"
	data-wp-on--click="actions.toggle"
	data-wp-on--keydown="actions.handleTriggerKeyDown"
	data-wp-bind--aria-expanded="state.ariaExpanded"
	data-wp-text="state.displayValue"
	data-bsui-select-trigger
	aria-haspopup="listbox"
	aria-expanded="false"
	aria-controls="<?php echo esc_attr( $listbox_id ); ?>"
>
	<?php echo esc_html( $context['bsui/select']['placeholder'] ?? 'Select...' ); ?>
</button>
