<?php
$src      = ! empty( $a['src'] ) ? $a['src'] : '';
$alt      = ! empty( $a['alt'] ) ? $a['alt'] : '';
$fallback = ! empty( $a['fallback'] ) ? $a['fallback'] : '';
$has_src  = '' !== $src;
?>
<span
	data-wp-interactive="bsui/avatar"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'loaded' => $has_src,
		'error'  => false,
	) ) ); ?>'
>
	<?php if ( $has_src ) : ?>
	<img
		src="<?php echo esc_url( $src ); ?>"
		alt="<?php echo esc_attr( $alt ); ?>"
		data-wp-on--load="actions.handleLoad"
		data-wp-on--error="actions.handleError"
		data-wp-bind--hidden="state.imageHidden"
	/>
	<?php endif; ?>
	<?php if ( '' !== $fallback ) : ?>
	<span
		data-wp-bind--hidden="state.fallbackHidden"
		aria-hidden="true"
		<?php if ( $has_src ) echo 'hidden'; ?>
	><?php echo esc_html( $fallback ); ?></span>
	<?php endif; ?>
</span>
