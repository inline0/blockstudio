<?php
$checked   = ! empty( $a['checked'] ) ? $a['checked'] : '';
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$bind_text = ! empty( $a['bindText'] ) ? $a['bindText'] : '';
$disabled  = ! empty( $a['disabled'] );
$initial   = ! empty( $a['defaultChecked'] ) ? 'true' : 'false';
?>
<span
	data-bsui-checkbox
	data-wp-interactive="bsui/checkbox"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'checked'       => ! empty( $a['defaultChecked'] ),
		'indeterminate' => ! empty( $a['indeterminate'] ),
		'disabled'      => (bool) $disabled,
		'value'         => (string) ( $a['value'] ?? 'on' ),
	) ) ); ?>'
>
	<button
		type="button"
		data-bsui-focus
		role="checkbox"
		aria-checked="<?php echo esc_attr( ! empty( $a['indeterminate'] ) ? 'mixed' : $initial ); ?>"
		<?php if ( $checked ) : ?>
		data-wp-bind--aria-checked="<?php echo esc_attr( $checked ); ?>"
		<?php else : ?>
		data-wp-bind--aria-checked="state.ariaChecked"
		<?php endif; ?>
		data-wp-on--click="<?php echo esc_attr( $on_change ? $on_change : 'actions.toggle' ); ?>"
		<?php if ( $disabled ) echo 'disabled'; ?>
	></button>
	<?php $checkbox_name = (string) ( $a['name'] ?? '' ); ?>
	<?php if ( '' !== $checkbox_name ) : ?>
	<input type="hidden" name="<?php echo esc_attr( $checkbox_name ); ?>" data-wp-bind--value="state.formValue" />
	<?php endif; ?>
	<?php if ( $bind_text ) : ?>
	<span data-wp-text="<?php echo esc_attr( $bind_text ); ?>"><?php echo esc_html( $a['label'] ?? '' ); ?></span>
	<?php else : ?>
	<?php echo bs_block( array( 'name' => 'bsui/label', 'data' => array( 'text' => (string) ( $a['label'] ?? '' ) ) ) ); ?>
	<?php endif; ?>
</span>
