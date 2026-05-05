<?php
$value         = $a['value'] ?? '';
$default_value = $context['bsui/tabs']['defaultValue'] ?? '';
$is_active     = $value !== '' && $value === $default_value;
$tab_id        = 'ui-tab-' . sanitize_title( $value );
$panel_id      = 'ui-tabpanel-' . sanitize_title( $value );
?>
<button
	data-bsui-focus
	data-wp-interactive="bsui/tabs"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'value' => $value ) ) ); ?>'
	data-wp-on--click="actions.selectTab"
	data-wp-on--keydown="actions.handleKeyDown"
	data-wp-bind--aria-selected="state.ariaSelected"
	data-wp-bind--tabindex="state.tabIndex"
	data-tab-value="<?php echo esc_attr( $value ); ?>"
	aria-controls="<?php echo esc_attr( $panel_id ); ?>"
	id="<?php echo esc_attr( $tab_id ); ?>"
	role="tab"
	aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
	tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
>
	<RichText attribute="title" tag="span" placeholder="Tab" />
</button>
