<?php
$href    = ! empty( $a['href'] ) ? $a['href'] : '#';
$current = ! empty( $a['current'] );
?>
<li>
	<a
		data-bsui-focus
		data-bsui-pagination-item
		href="<?php echo esc_url( $href ); ?>"
		<?php if ( $current ) echo 'aria-current="page"'; ?>
	>
		<RichText attribute="label" tag="span" placeholder="1" />
	</a>
</li>
