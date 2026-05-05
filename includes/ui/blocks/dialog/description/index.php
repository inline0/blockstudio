<?php
$content = $a['content'] ?? '';
?>
<p data-bsui-dialog-description><?php echo wp_kses_post( $content ); ?></p>
