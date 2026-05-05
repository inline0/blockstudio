<?php
$default_value = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '';
$multiple      = ! empty( $a['multiple'] );
$name          = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$placeholder   = ! empty( $a['placeholder'] ) ? $a['placeholder'] : 'Select...';
$on_change     = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$listbox_id    = wp_unique_id( 'select-listbox-' );

$value = $multiple ? array() : $default_value;
?>
<div
	data-wp-interactive="bsui/select"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'             => false,
		'value'            => $value,
		'label'            => '',
		'labels'           => array(),
		'multiple'         => $multiple,
		'activeIndex'      => -1,
		'activeDescendant' => '',
		'placeholder'      => $placeholder,
		'name'             => $name,
		'listboxId'        => $listbox_id,
	) ) ); ?>'
	data-bsui-select-root
	data-wp-on-document--click="actions.handleOutsideClick"
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
	style="display:inline-block;position:relative"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/select-trigger', 'bsui/select-popup', 'bsui/select-value' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/select-trigger', array() ),
			array( 'bsui/select-popup', array() ),
		) ) ); ?>'
	/>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		data-wp-bind--value="state.selectedValue"
		value="<?php echo esc_attr( $default_value ); ?>"
	/>
	<?php endif; ?>
</div>
