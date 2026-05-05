<?php
$disabled = ! empty( $a['disabled'] );
?>
<button
	data-bsui-focus
	data-wp-interactive="bsui/toolbar"
	data-bsui-toolbar-button
	tabindex="-1"
	<?php if ( $disabled ) echo 'disabled'; ?>
>
	<RichText attribute="label" tag="span" placeholder="Button" />
</button>
