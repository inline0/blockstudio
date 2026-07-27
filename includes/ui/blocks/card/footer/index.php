<?php
$justify_map = array(
	'start'   => 'flex-start',
	'center'  => 'center',
	'end'     => 'flex-end',
	'between' => 'space-between',
);
$justify     = $justify_map[ $a['justify'] ?? 'start' ] ?? 'flex-start';
?>
<div data-bsui-card-footer<?php if ( 'flex-start' !== $justify ) echo ' style="--bsui-card-footer-justify:' . esc_attr( $justify ) . '"'; ?>>
	<InnerBlocks />
</div>
