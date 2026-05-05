<?php
$href = ! empty( $a['href'] ) ? $a['href'] : '#';
?>
<li>
	<a data-bsui-focus data-bsui-nav-link href="<?php echo esc_url( $href ); ?>">
		<RichText attribute="label" tag="span" placeholder="Link" />
	</a>
</li>
