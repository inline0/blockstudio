<?php $link = $a['fieldName'];
if ($link): ?>
    <a href="<?php echo $link['url']; ?>">
        <?php echo $link['title']; ?>
    </a>
<?php endif; ?>
