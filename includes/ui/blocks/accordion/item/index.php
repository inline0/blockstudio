<?php
$value          = $a['value'] ?? '';
$item_disabled  = ! empty( $a['disabled'] );
$root_disabled  = ! empty( $context['bsui/accordion']['disabled'] ?? false );
$root_value     = $context['bsui/accordion']['defaultValue'] ?? '';
$is_open        = $value !== '' && $value === $root_value;
$disabled       = $item_disabled || $root_disabled;
?>
<div
	data-wp-interactive="bsui/accordion"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'itemValue' => $value ) ) ); ?>'
	<?php if ( $disabled ) echo 'data-disabled'; ?>
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/accordion-trigger', 'bsui/accordion-panel' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'bsui/accordion-trigger', array() ),
			array( 'bsui/accordion-panel', array() ),
		) ) ); ?>'
	/>
</div>
