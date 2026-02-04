<?php
/**
 * Main template file.
 *
 * @package Blockstudio_Test_Theme
 */

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}

get_footer();
