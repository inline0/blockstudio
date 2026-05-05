<?php
$name     = ! empty( $a['name'] ) ? $a['name'] : '';
$invalid  = ! empty( $a['invalid'] );
$disabled = ! empty( $a['disabled'] );
$in_form  = ! empty( $context['bsui/form'] );
$field_id = wp_unique_id( 'bsui-field-' );
?>
<div
	<?php if ( $in_form ) : ?>
		data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'fieldName' => $name ) ) ); ?>'
		data-wp-init="callbacks.initField"
		data-wp-on--click="actions.clearFieldError"
		data-wp-class--bs-ui-invalid="state.isFieldInvalid"
	<?php endif; ?>
	<?php if ( $invalid ) echo 'data-invalid'; ?>
	<?php if ( $disabled ) echo 'data-disabled'; ?>
	data-bsui-field-root
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/text', 'bsui/field-label', 'bsui/field-description', 'bsui/field-error', 'bsui/input', 'bsui/textarea', 'bsui/number-field', 'bsui/select', 'bsui/checkbox', 'bsui/switch', 'bsui/radio-group' ) ) ); ?>'
	/>
</div>
