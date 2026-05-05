<?php
$value          = ! empty( $a['value'] ) ? $a['value'] : '';
$disabled       = ! empty( $a['disabled'] );
$root_disabled  = ! empty( $context['bsui/radio-group']['disabled'] ?? false );
$default_value  = $context['bsui/radio-group']['defaultValue'] ?? '';
$is_checked     = $value !== '' && $value === $default_value;
$is_disabled    = $disabled || $root_disabled;
$bind_text      = ! empty( $a['bindText'] ) ? $a['bindText'] : '';
?>
<label data-bsui-radio>
	<div
		data-wp-interactive="bsui/radio-group"
		data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'radioValue' => $value ) ) ); ?>'
		style="display:contents"
	>
		<button
			data-bsui-focus
			data-wp-on--click="actions.select"
			data-wp-bind--aria-checked="state.ariaChecked"
			data-wp-bind--tabindex="state.tabIndex"
			data-value="<?php echo esc_attr( $value ); ?>"
			role="radio"
			aria-checked="<?php echo $is_checked ? 'true' : 'false'; ?>"
			tabindex="<?php echo $is_checked ? '0' : '-1'; ?>"
			<?php if ( $is_disabled ) echo 'aria-disabled="true"'; ?>
		></button>
	</div>
	<?php if ( $bind_text ) : ?>
	<span data-wp-text="<?php echo esc_attr( $bind_text ); ?>"><?php echo esc_html( $a['label'] ?? '' ); ?></span>
	<?php else : ?>
	<RichText attribute="label" tag="span" placeholder="Option" />
	<?php endif; ?>
</label>
