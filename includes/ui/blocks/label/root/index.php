<?php
$for       = (string) ( $a['for'] ?? '' );
$bind_text = (string) ( $a['bindText'] ?? '' );
?>
<label data-bsui-label <?php if ( '' !== $for ) : ?>for="<?php echo esc_attr( $for ); ?>"<?php endif; ?>><?php if ( '' !== $bind_text ) : ?><span data-wp-text="<?php echo esc_attr( $bind_text ); ?>"><?php echo esc_html( wp_strip_all_tags( (string) ( $a['text'] ?? '' ) ) ); ?></span><?php else : ?><RichText attribute="text" tag="span" placeholder="Label" /><?php endif; ?></label>
