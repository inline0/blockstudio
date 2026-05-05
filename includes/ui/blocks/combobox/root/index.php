<?php
$placeholder = ! empty( $a['placeholder'] ) ? $a['placeholder'] : 'Search...';
$name        = ! empty( $a['name'] ) ? $a['name'] : '';
?>
<div
	data-wp-interactive="bsui/combobox"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'        => false,
		'query'       => '',
		'value'       => '',
		'label'       => '',
		'placeholder' => $placeholder,
		'name'        => $name,
		'activeIndex' => -1,
	) ) ); ?>'
	data-bsui-combobox-root
	data-wp-on-document--click="actions.handleOutsideClick"
	style="display: inline-block; position: relative;"
>
	<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
		'bsui/combobox-input',
		'bsui/combobox-popup',
	) ) ); ?>' />
	<?php if ( '' !== $name ) : ?>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" data-wp-bind--value="state.selectedValue" value="" />
	<?php endif; ?>
</div>
