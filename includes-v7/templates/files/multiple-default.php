<?php $files = $a['fieldName'] ?? [];
foreach ($files as $file): ?>
	<?php wp_get_attachment_image($file['ID'], 'full'); ?>
<?php endforeach; ?>
