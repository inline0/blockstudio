<?php
$content = $a['content'] ?? '';
?>
<h3 data-bsui-dialog-title><?php echo wp_kses_post( $content ); ?></h3>
