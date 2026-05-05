<?php
$placeholder = $context['bsui/combobox']['placeholder'] ?? 'Search...';
?>
<input
	data-bsui-focus
	data-wp-interactive="bsui/combobox"
	data-wp-on--input="actions.handleInput"
	data-wp-on--focus="actions.handleFocus"
	data-wp-on--keydown="actions.handleKeyDown"
	data-bsui-combobox-input
	type="text"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
	autocomplete="off"
	role="combobox"
	aria-expanded="false"
	aria-autocomplete="list"
/>
