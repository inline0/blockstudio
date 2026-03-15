<?php
/**
 * Native WP block render template.
 *
 * @package Blockstudio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'native-wp-block-test' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="native-wp-block-text">Native WP block registered by Blockstudio</p>
</div>
