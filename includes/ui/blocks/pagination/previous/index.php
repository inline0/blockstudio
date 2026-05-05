<?php
$href = ! empty( $a['href'] ) ? $a['href'] : '#';
?>
<li>
	<a data-bsui-focus data-bsui-pagination-prev href="<?php echo esc_url( $href ); ?>">
		<span aria-hidden="true">&larr;</span> Previous
	</a>
</li>
