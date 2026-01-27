<?php $files = $a['fieldName'] ?? [];
foreach ($files as $file): ?>
	<img src="<?php echo $file; ?>">
<?php endforeach; ?>
