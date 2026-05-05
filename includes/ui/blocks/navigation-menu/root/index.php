<?php
?>
<nav data-wp-interactive="bsui/navigation-menu" data-bsui-nav-menu>
	<ul>
		<InnerBlocks allowedBlocks='<?php echo esc_attr( wp_json_encode( array(
			'bsui/navigation-menu-item',
			'bsui/navigation-menu-link',
		) ) ); ?>' />
	</ul>
</nav>
