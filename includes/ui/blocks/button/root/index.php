<?php
$variant  = $a['variant'] ?? 'default';
$size     = $a['size'] ?? 'default';
$href     = ! empty( $a['href'] ) ? $a['href'] : '';
$disabled = ! empty( $a['disabled'] );
$tag      = '' !== $href ? 'a' : 'button';
$icon     = isset( $a['icon']['element'] ) && is_string( $a['icon']['element'] ) ? $a['icon']['element'] : '';
$icon_position = 'right' === ( $a['iconPosition'] ?? 'left' ) ? 'right' : 'left';
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
	<?php if ( '' !== $icon && 'left' === $icon_position ) : ?>
	<span data-bsui-button-icon aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon SVG is rendered by bs_icon(). ?></span>
	<?php endif; ?>
	<RichText attribute="label" tag="span" placeholder="Button" />
	<?php if ( '' !== $icon && 'right' === $icon_position ) : ?>
	<span data-bsui-button-icon aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon SVG is rendered by bs_icon(). ?></span>
	<?php endif; ?>
</<?php echo $tag; ?>>
