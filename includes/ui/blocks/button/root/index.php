<?php
$variant  = $a['variant'] ?? 'default';
$size     = $a['size'] ?? 'default';
$href     = ! empty( $a['href'] ) ? $a['href'] : '';
$disabled = ! empty( $a['disabled'] );
$tag      = '' !== $href ? 'a' : 'button';
?>
<<?php echo $tag; ?>
	data-bsui-focus
	data-bsui-button
	data-variant="<?php echo esc_attr( $variant ); ?>"
	data-size="<?php echo esc_attr( $size ); ?>"
	<?php if ( '' !== $href ) : ?>
	href="<?php echo esc_url( $href ); ?>"
	<?php endif; ?>
	<?php if ( $disabled ) : ?>
	disabled
	aria-disabled="true"
	<?php endif; ?>
>
	<RichText attribute="label" tag="span" placeholder="Button" />
</<?php echo $tag; ?>>
