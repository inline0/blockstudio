<div useBlockProps class="blockstudio-test__block" id="<?php echo str_replace( '/', '-', $b['name'] ); ?>">
  <h1>ID: <?php echo str_replace( '/', '-', $b['name'] ); ?></h1>
  <div data-wp-interactive="blockstudioDirectives"
       data-wp-context='{ "isVisible": true, "label": "Context value" }'>
    <p id="visible-content" data-wp-bind--hidden="!context.isVisible">
      This content is visible by default.
    </p>
    <p id="hidden-content" data-wp-bind--hidden="context.isVisible">
      This content is hidden by default.
    </p>
    <span id="context-label" data-wp-text="context.label">placeholder</span>
  </div>
</div>
