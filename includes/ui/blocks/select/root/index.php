<?php
$default_value = ! empty( $a['defaultValue'] ) ? $a['defaultValue'] : '';
$multiple      = ! empty( $a['multiple'] );
$name          = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$placeholder   = ! empty( $a['placeholder'] ) ? $a['placeholder'] : 'Select...';
$on_change     = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$listbox_id    = wp_unique_id( 'select-listbox-' );

$value = $multiple ? array() : $default_value;

$default_label = '';

$options = array();
if ( ! empty( $a['options'] ) ) {
	$decoded = is_array( $a['options'] ) ? $a['options'] : json_decode( (string) $a['options'], true );
	if ( is_array( $decoded ) ) {
		$options = $decoded;
	}
}

if ( ! $multiple && '' !== $default_value ) {
	foreach ( $options as $option ) {
		if ( (string) ( $option['value'] ?? '' ) === (string) $default_value ) {
			$default_label = (string) ( $option['label'] ?? $default_value );
			break;
		}
	}
}
?>
<div
	data-wp-interactive="bsui/select"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'             => false,
		'value'            => $value,
		'label'            => $default_label,
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
	<?php if ( ! empty( $options ) ) : ?>
		<button
			type="button"
			data-bsui-focus
			data-wp-interactive="bsui/select"
			data-wp-on--click="actions.toggle"
			data-wp-on--keydown="actions.handleTriggerKeyDown"
			data-wp-bind--aria-expanded="state.ariaExpanded"
			data-wp-text="state.displayValue"
			data-bsui-select-trigger
			aria-haspopup="listbox"
			aria-expanded="false"
			aria-controls="<?php echo esc_attr( $listbox_id ); ?>"
		><?php echo esc_html( $placeholder ); ?></button>
		<div
			data-bsui-focus
			data-wp-interactive="bsui/select"
			data-wp-on--keydown="actions.handleListboxKeyDown"
			data-wp-bind--aria-multiselectable="state.ariaMultiSelectable"
			data-wp-bind--aria-activedescendant="state.activeDescendant"
			id="<?php echo esc_attr( $listbox_id ); ?>"
			role="listbox"
			tabindex="-1"
			hidden
		>
			<?php
			foreach ( $options as $option ) :
				$opt_value    = (string) ( $option['value'] ?? '' );
				$opt_label    = (string) ( $option['label'] ?? $opt_value );
				$opt_disabled = ! empty( $option['disabled'] );
				$opt_selected = '' !== $opt_value && (string) $default_value === $opt_value;
				$opt_id       = wp_unique_id( 'select-option-' );
				?>
				<div
					data-wp-interactive="bsui/select"
					data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'optionValue' => $opt_value, 'optionLabel' => $opt_label ) ) ); ?>'
					data-wp-on--click="actions.selectOption"
					data-wp-bind--aria-selected="state.ariaSelected"
					data-value="<?php echo esc_attr( $opt_value ); ?>"
					id="<?php echo esc_attr( $opt_id ); ?>"
					role="option"
					tabindex="-1"
					aria-selected="<?php echo $opt_selected ? 'true' : 'false'; ?>"
					<?php if ( $opt_disabled ) echo 'aria-disabled="true"'; ?>
				><?php echo esc_html( $opt_label ); ?></div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<InnerBlocks
			allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/select-trigger', 'bsui/select-popup', 'bsui/select-value' ) ) ); ?>'
			template='<?php echo esc_attr( wp_json_encode( array(
				array( 'bsui/select-trigger', array() ),
				array( 'bsui/select-popup', array() ),
			) ) ); ?>'
		/>
	<?php endif; ?>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		data-wp-bind--value="state.selectedValue"
		value="<?php echo esc_attr( $default_value ); ?>"
	/>
	<?php endif; ?>
</div>
