<?php $for = (string) ( $a['for'] ?? '' ); ?>
<label data-bsui-label <?php if ( '' !== $for ) : ?>for="<?php echo esc_attr( $for ); ?>"<?php endif; ?>><RichText attribute="text" tag="span" placeholder="Label" /></label>
