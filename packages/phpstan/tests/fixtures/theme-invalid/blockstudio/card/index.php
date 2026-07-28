<?php wp_enqueue_script( 'manual-card-script' ); ?>
<article
	data-wp-interactive="example/card"
	data-wp-context="<?php echo $context; ?>"
	data-wp-bind--aria-expanded="state.missing"
	class="bg-[#123456] bg-definitely-not-real"
>
	<h2><?= $a['title'] ?></h2>
	<button data-wp-on--click="actions.missing">Open</button>
</article>
