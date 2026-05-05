<?php
$in_form = ! empty( $context['bsui/form'] );
?>
<?php if ( $in_form ) : ?>
<span
	role="alert"
	data-wp-bind--hidden="!state.hasFieldError"
	data-wp-text="state.fieldError"
	hidden
></span>
<?php else : ?>
<span role="alert"><RichText attribute="text" tag="span" placeholder="Error message" /></span>
<?php endif; ?>
