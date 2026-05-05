<?php
$value    = ! empty( $a['value'] ) ? $a['value'] : '';
$disabled = ! empty( $a['disabled'] );
?>
<button
	data-bsui-focus
	data-wp-interactive="bsui/toggle-group"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'itemValue' => $value ) ) ); ?>'
	data-wp-on--click="actions.toggle"
	data-wp-bind--aria-pressed="state.ariaPressed"
	data-bsui-toggle-group-item
	aria-pressed="false"
	tabindex="-1"
	<?php if ( $disabled ) echo 'disabled'; ?>
>
	<RichText attribute="label" tag="span" placeholder="Option" />
</button>
