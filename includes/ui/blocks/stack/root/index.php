<?php
$scale = array(
	'0'    => '0',
	'0.5'  => '0.125rem',
	'1'    => '0.25rem',
	'1.5'  => '0.375rem',
	'2'    => '0.5rem',
	'2.5'  => '0.625rem',
	'3'    => '0.75rem',
	'3.5'  => '0.875rem',
	'4'    => '1rem',
	'5'    => '1.25rem',
	'6'    => '1.5rem',
	'7'    => '1.75rem',
	'8'    => '2rem',
	'9'    => '2.25rem',
	'10'   => '2.5rem',
	'11'   => '2.75rem',
	'12'   => '3rem',
	'14'   => '3.5rem',
	'16'   => '4rem',
	'20'   => '5rem',
	'24'   => '6rem',
);

$direction = ! empty( $a['direction'] ) ? $a['direction'] : 'row';
$gap_raw   = isset( $a['gap'] ) ? $a['gap'] : '2';
$gap       = isset( $scale[ $gap_raw ] ) ? $scale[ $gap_raw ] : $gap_raw;
$default   = 'column' === $direction ? 'flex-start' : 'center';
$align     = ! empty( $a['align'] ) ? $a['align'] : $default;
$justify   = ! empty( $a['justify'] ) ? $a['justify'] : '';
$wrap      = filter_var( $a['wrap'] ?? true, FILTER_VALIDATE_BOOLEAN );

$style = '--bsui-stack-gap:' . esc_attr( $gap ) . ';'
	. ' --bsui-stack-direction:' . esc_attr( $direction ) . ';'
	. ' --bsui-stack-align:' . esc_attr( $align ) . ';';

if ( $justify ) {
	$style .= ' --bsui-stack-justify:' . esc_attr( $justify ) . ';';
}

if ( $wrap ) {
	$style .= ' --bsui-stack-wrap:wrap;';
}
?>
<div data-bsui-stack style="<?php echo $style; ?>">
	<InnerBlocks />
</div>
