<?php $link = $a['fieldName'];
if ($link): ?>
    <a href="<?php echo $link['url']; ?>"
       target="<?php echo $link['opensInNewTab'] ? '_blank' : '_self'; ?>">
        <?php echo $link['title']; ?>
    </a>
<?php endif; ?>
