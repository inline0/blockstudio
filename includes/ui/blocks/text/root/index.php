<?php
$variant = ! empty( $a['variant'] ) ? $a['variant'] : 'body';
$tag     = ! empty( $a['tag'] ) ? $a['tag'] : 'p';
$content = $a['content'] ?? '';

if ( $content ) :
?>
<RichText attribute="content" tag="<?php echo esc_attr( $tag ); ?>" data-bsui-text data-variant="<?php echo esc_attr( $variant ); ?>" placeholder="Text" />
<?php else : ?>
<<?php echo esc_attr( $tag ); ?> data-bsui-text data-variant="<?php echo esc_attr( $variant ); ?>"></<?php echo esc_attr( $tag ); ?>>
<?php endif; ?>
