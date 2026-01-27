<div class="blockstudio-test__block" id="<?php echo str_replace('/', '-', $b['name']); ?>">
  <MediaPlaceholder
    attribute="files"
    labels="<?php echo esc_attr(wp_json_encode(['title' => 'Test title', 'instructions' => 'Test instructions'])); ?>""
  />
  <?php foreach (($a['files'] ?? []) as $file) {
      echo wp_get_attachment_image($file['ID']);
  } ?>
</div>
