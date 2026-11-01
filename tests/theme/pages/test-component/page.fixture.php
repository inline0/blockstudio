<section class="component-page" data-page="<?php echo esc_attr( $data['name'] ); ?>" data-context="<?php echo esc_attr( $request['context'] ); ?>">
	<h1><?php echo esc_html( $data['heading'] ); ?></h1>
	<div class="component-page-slot"><?php echo $request['slot']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
</section>
