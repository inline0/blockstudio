<?php
$disabled = ! empty( $context['bsui/collapsible']['disabled'] ?? false );
$open     = ! empty( $context['bsui/collapsible']['defaultOpen'] ?? false );
?>
<div
	data-wp-interactive="bsui/collapsible"
	data-wp-on--click="actions.toggle"
	data-wp-bind--aria-expanded="state.ariaExpanded"
	data-bsui-collapsible-trigger
	aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
	<?php if ( $disabled ) echo 'disabled'; ?>
	style="display:inline"
>
	<?php echo $a['trigger'] ?? ''; ?>
</div>
