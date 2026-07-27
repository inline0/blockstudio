<?php
$checked   = ! empty( $a['checked'] ) ? $a['checked'] : '';
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$bind_text = ! empty( $a['bindText'] ) ? $a['bindText'] : '';
$disabled  = ! empty( $a['disabled'] );
$initial   = ! empty( $a['defaultChecked'] ) ? 'true' : 'false';
?>
<label
	data-bsui-switch
	data-wp-interactive="bsui/switch"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'checked'        => ! empty( $a['defaultChecked'] ),
		'disabled'       => (bool) $disabled,
		'readOnly'       => ! empty( $a['readOnly'] ),
		'required'       => ! empty( $a['required'] ),
		'checkedValue'   => (string) ( $a['checkedValue'] ?? 'on' ),
		'uncheckedValue' => (string) ( $a['uncheckedValue'] ?? '' ),
	) ) ); ?>'
>
	<button
		type="button"
		data-bsui-focus
		role="switch"
		aria-checked="<?php echo esc_attr( $initial ); ?>"
		<?php if ( $checked ) : ?>
		data-wp-bind--aria-checked="<?php echo esc_attr( $checked ); ?>"
		<?php else : ?>
		data-wp-bind--aria-checked="state.ariaChecked"
		<?php endif; ?>
		data-wp-on--click="<?php echo esc_attr( $on_change ? $on_change : 'actions.toggle' ); ?>"
		<?php if ( $disabled ) echo 'disabled'; ?>
	>
		<span data-bsui-switch-thumb></span>
	</button>
	<?php $switch_name = (string) ( $a['name'] ?? ( $a['nameAlt'] ?? '' ) ); ?>
	<?php if ( '' !== $switch_name ) : ?>
	<input type="hidden" name="<?php echo esc_attr( $switch_name ); ?>" data-wp-bind--value="state.formValue" />
	<?php endif; ?>
	<?php if ( $bind_text ) : ?>
	<span data-wp-text="<?php echo esc_attr( $bind_text ); ?>"><?php echo esc_html( $a['label'] ?? '' ); ?></span>
	<?php else : ?>
	<RichText attribute="label" tag="span" placeholder="Switch" />
	<?php endif; ?>
</label>
