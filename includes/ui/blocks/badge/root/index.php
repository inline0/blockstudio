<?php
$variant = $a['variant'] ?? 'default';
?>
<span data-bsui-badge data-variant="<?php echo esc_attr( $variant ); ?>">
	<RichText attribute="label" tag="span" placeholder="Badge" />
</span>
