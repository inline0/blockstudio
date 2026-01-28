<div useBlockProps data-wp-interactive="myPlugin" data-wp-context='{ "isOpen": false }' data-wp-watch="callbacks.logIsOpen" class="blockstudio-test__block" id="<?php echo str_replace( "/", "-", $b["name"] ); ?>">
  <h1>ID: <?php echo str_replace( "/", "-", $b["name"] ); ?></h1>
  <code>Attributes: <?php echo json_encode( $a ); ?></code>
  <code>Block: <?php echo json_encode( $b ); ?></code>
  <div x-data="{ open: false }">
    <button x-on:click="open = !open">Expand</button>
    <span x-show="open">
        Content...
    </span>
  </div>
</div>
