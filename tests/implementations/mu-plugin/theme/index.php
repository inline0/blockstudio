<?php wp_head(); ?>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
<article><?php the_content(); ?></article>
<?php endwhile; endif; ?>
<?php wp_footer(); ?>
</body>
