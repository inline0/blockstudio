<?php
$name        = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$placeholder = ! empty( $a['placeholder'] ) ? $a['placeholder'] : 'Pick a date';
$disabled    = ! empty( $a['disabled'] );
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';
$input_id    = wp_unique_id( 'bsui-date-input-' );
?>
<div
	data-wp-interactive="bsui/date-input"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'open'  => false,
		'value' => '',
	) ) ); ?>'
	data-bsui-date-input
	data-wp-on-document--click="actions.handleOutsideClick"
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
	style="display:inline-block;position:relative"
>
	<div data-bsui-date-input-trigger>
		<input
			data-bsui-focus
			data-wp-on--click="actions.toggle"
			data-wp-on--input="actions.handleInput"
			data-wp-bind--value="state.displayValue"
			type="text"
			id="<?php echo esc_attr( $input_id ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="off"
			<?php if ( $disabled ) echo 'disabled'; ?>
		/>
		<button
			data-wp-on--click="actions.toggle"
			type="button"
			tabindex="-1"
			aria-label="Open calendar"
			<?php if ( $disabled ) echo 'disabled'; ?>
		></button>
	</div>
	<div data-bsui-date-input-popup hidden>
		<?php echo bs_block( array( 'name' => 'bsui/calendar', 'data' => array() ) ); ?>
	</div>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		data-wp-bind--value="state.isoValue"
	/>
	<?php endif; ?>
</div>
