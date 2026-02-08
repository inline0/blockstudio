<?php
wp_interactivity_state(
	'blockstudioServerState',
	array(
		'message' => 'Hello from server state',
		'count'   => 42,
	)
);
?>
<div useBlockProps class="blockstudio-test__block" id="<?php echo str_replace( '/', '-', $b['name'] ); ?>">
  <h1>ID: <?php echo str_replace( '/', '-', $b['name'] ); ?></h1>
  <div data-wp-interactive="blockstudioServerState">
    <p id="server-message" data-wp-text="state.message">placeholder</p>
    <p id="server-count" data-wp-text="state.count">0</p>
  </div>
</div>
