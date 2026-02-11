<div class="preload-no-defaults" data-title="<?php echo esc_attr( $a['title'] ?? '' ); ?>" data-count="<?php echo esc_attr( $a['count'] ?? '' ); ?>" data-active="<?php echo esc_attr( $a['active'] ? 'true' : 'false' ); ?>">
  <span class="preload-title"><?php echo esc_html( $a['title'] ?? '' ); ?></span>
  <span class="preload-count"><?php echo esc_html( $a['count'] ?? '' ); ?></span>
  <span class="preload-active"><?php echo $a['active'] ? 'true' : 'false'; ?></span>
</div>
