<?php
$href = ! empty( $a['href'] ) ? $a['href'] : '#';
?>
<li>
	<a data-bsui-focus data-bsui-pagination-next href="<?php echo esc_url( $href ); ?>">
		Next <span aria-hidden="true">&rarr;</span>
	</a>
</li>
