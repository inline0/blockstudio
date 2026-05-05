<?php
$value         = $a['value'] ?? '';
$default_value = $context['bsui/tabs']['defaultValue'] ?? '';
$is_active     = $value !== '' && $value === $default_value;
$tab_id        = 'ui-tab-' . sanitize_title( $value );
$panel_id      = 'ui-tabpanel-' . sanitize_title( $value );
?>
<div
	data-bsui-focus
	data-wp-interactive="bsui/tabs"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'value' => $value ) ) ); ?>'
	data-wp-bind--hidden="state.panelHidden"
	id="<?php echo esc_attr( $panel_id ); ?>"
	aria-labelledby="<?php echo esc_attr( $tab_id ); ?>"
	role="tabpanel"
	data-wp-bind--tabindex="state.panelTabIndex"
	tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
	<?php if ( ! $is_active ) echo 'hidden'; ?>
>
	<InnerBlocks />
</div>
