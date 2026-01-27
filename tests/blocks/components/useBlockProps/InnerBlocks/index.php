<?php
$allowedBlocks = [
  'core/heading',
  'core/paragraph',
  'layout/row-block',
  'component/icon-card',
];
?>

<InnerBlocks 
  style="padding: 1rem; background: #f1f1f1;"
  class="blockstudio-test__block test test2 test3"
  useBlockProps
  test
  asdasd
  hello="asd"
  id="<?php echo str_replace( "/", "-", $b["name"] ); ?>"
  allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowedBlocks ) ); ?>"
/>