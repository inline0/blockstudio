<?php
$listbox_id = $context['bsui/select']['listboxId'] ?? '';
?>
<div
	data-bsui-focus
	data-wp-interactive="bsui/select"

	data-wp-on--keydown="actions.handleListboxKeyDown"
	data-wp-bind--aria-multiselectable="state.ariaMultiSelectable"
	data-wp-bind--aria-activedescendant="state.activeDescendant"
	id="<?php echo esc_attr( $listbox_id ); ?>"
	role="listbox"
	tabindex="-1"
	hidden
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/select-option' ) ) ); ?>'
	/>
</div>
