<?php $size = in_array( (string) ( $a['size'] ?? '4' ), array( '1', '2', '3', '4', '6', '8' ), true ) ? (string) ( $a['size'] ?? '4' ) : '4'; ?>
<div data-bsui-space data-size="<?php echo esc_attr( $size ); ?>" aria-hidden="true"></div>
