<div class="preload-repeater">
  <span class="preload-heading"><?php echo esc_html( $a['heading'] ?? '' ); ?></span>
  <?php if ( ! empty( $a['items'] ) ) : ?>
    <?php foreach ( $a['items'] as $i => $item ) : ?>
      <div class="preload-item" data-index="<?php echo esc_attr( $i ); ?>">
        <span class="preload-item-text"><?php echo esc_html( $item['text'] ?? '' ); ?></span>
        <span class="preload-item-number"><?php echo esc_html( $item['number'] ?? '' ); ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
