<div useBlockProps class="blockstudio-test__block" id="<?php echo str_replace( "/", "-", $b["name"] ); ?>">
  <h1>ID: <?php echo str_replace( "/", "-", $b["name"] ); ?></h1>
  <code>Attributes: <?php echo json_encode( $a ); ?></code>
  <code>Block: <?php echo json_encode( $b ); ?></code>
  <div data-wp-interactive="blockstudioTest"
       data-wp-context='{ "isOpen": false }'
       data-wp-watch="callbacks.logIsOpen">
    <button
      data-wp-on--click="actions.toggle"
      data-wp-bind--aria-expanded="context.isOpen"
      aria-controls="content"
    >
      Toggle
    </button>
    <p id="content" data-wp-bind--hidden="!context.isOpen">
      Hello from an interactive block!
    </p>
  </div>
</div>
