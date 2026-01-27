<InnerBlocks 
  style="padding: 1rem; background: #f1f1f1;"
  class="blockstudio-test__block test test2 test3"
  id="<?php echo str_replace("/", "-", $b["name"]); ?>"
  tag="section"
  data-attr="console.log('click')"
  template="<?php echo esc_attr(wp_json_encode([
    ['core/heading', ['placeholder' => 'Book = Title']],
    ['core/paragraph', ['placeholder' => 'Summary']],
    ['blockstudio/type-text', ['text' => 'test', 'textClassSelect' => 'class-1']],
  ])); ?>"
  allowedBlocks="<?php echo esc_attr(wp_json_encode(['core/heading', 'core/paragraph'])); ?>"
/>
