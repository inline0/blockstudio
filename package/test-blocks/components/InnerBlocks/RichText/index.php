<?php
$allowedBlocks = [
  'core/heading',
  'core/paragraph',
];
?>

<div>
  <InnerBlocks 
    style="padding: 1rem; background: #f1f1f1;"
    class="blockstudio-test__block test test2 test3"
    data-attr="console.log('click')"
    allowedBlocks="<?php
      echo esc_attr(wp_json_encode($allowedBlocks)); 
    ?>"
  />
  <RichText 
    attribute="richtext"
    placeholder="Enter text here"
  />
</div>