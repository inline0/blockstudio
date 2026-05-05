<?php
$disabled = ! empty( $a['disabled'] );
?>
<div
	data-wp-interactive="bsui/menu"
	data-wp-on--click="actions.activateItem"
	role="menuitem"
	tabindex="-1"
	<?php if ( $disabled ) { echo 'aria-disabled="true"'; } ?>
>
	<RichText attribute="label" tag="span" placeholder="Menu item" />
</div>
