<?php
$checked   = ! empty( $a['checked'] ) ? $a['checked'] : '';
$on_change = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$bind_text = ! empty( $a['bindText'] ) ? $a['bindText'] : '';
$disabled  = ! empty( $a['disabled'] );
$initial   = ! empty( $a['defaultChecked'] ) ? 'true' : 'false';
?>
<label data-bsui-checkbox>
	<button
		data-bsui-focus
		role="checkbox"
		aria-checked="<?php echo esc_attr( $initial ); ?>"
		<?php if ( $checked ) echo 'data-wp-bind--aria-checked="' . esc_attr( $checked ) . '"'; ?>
		<?php if ( $on_change ) echo 'data-wp-on--click="' . esc_attr( $on_change ) . '"'; ?>
		<?php if ( $disabled ) echo 'disabled'; ?>
	></button>
	<?php if ( $bind_text ) : ?>
	<span data-wp-text="<?php echo esc_attr( $bind_text ); ?>"><?php echo esc_html( $a['label'] ?? '' ); ?></span>
	<?php else : ?>
	<RichText attribute="label" tag="span" placeholder="Checkbox" />
	<?php endif; ?>
</label>
