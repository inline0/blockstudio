<?php
$href = ! empty( $a['href'] ) ? $a['href'] : '#';
?>
<li data-bsui-breadcrumb-item>
	<a data-bsui-focus href="<?php echo esc_url( $href ); ?>">
		<RichText attribute="label" tag="span" placeholder="Page" />
	</a>
</li>
