<div class="blockstudio-test__block" id="<?php echo str_replace("/", "-", $b["name"]); ?>">
  <h1>ID: <?php echo str_replace("/", "-", $b["name"]); ?></h1>
  <p>Text: <?php echo $context['blockstudio/component-innerblocks-context']['text'] ?? ''; ?></p>
  <InnerBlocks 
    style="padding: 1rem; background: #f1f1f1;"
    class="blockstudio-test__block test test2 test3"
    tag="section"
    data-attr="console.log('click')"
    allowedBlocks="<?php echo esc_attr(wp_json_encode(['core/heading', 'core/paragraph'])); ?>"
  />
  <code>Attributes: <?php echo json_encode($a); ?></code>
  <code>Block: <?php echo json_encode($b); ?></code>
  <code>Context: <?php echo json_encode($context); ?></code>
</div>