<div class="blockstudio-test__block" id="<?php echo str_replace("/", "-", $b["name"]); ?>">
  <h1>ID: <?php echo str_replace("/", "-", $b["name"]); ?></h1>
  <p>Text: <?php echo $a['text']; ?></p>
  <InnerBlocks 
    style="padding: 1rem; background: #f1f1f1;"
    class="blockstudio-test__block test test2 test3"
    tag="section"
    data-attr="console.log('click')"
    allowedBlocks="<?php echo esc_attr(wp_json_encode(['blockstudio/component-innerblocks-context-child'])); ?>"
    template="<?php echo esc_attr(wp_json_encode([['blockstudio/component-innerblocks-context-child', []]])); ?>"
  />
  <code>Attributes: <?php echo json_encode($a); ?></code>
  <code>Block: <?php echo json_encode($b); ?></code>
</div>