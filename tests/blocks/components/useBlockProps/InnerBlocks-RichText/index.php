<?php
$allowedBlocks = [
  'core/heading',
  'core/paragraph',
];
?>

<div useBlockProps style="padding: 1rem; background: #f1f1f1;">
  <InnerBlocks
    style="padding: 1rem; background: #f1f1f1;"
    class="blockstudio-test__block test test2 test3"
    allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowedBlocks ) ); ?>"
  />
  <RichText
    style="padding: 1rem; background: #f1f1f1;"
    attribute="richtext"
    placeholder="Enter text here"
  />
</div>