<?php
$block_name      = ! empty( $a['block'] ) ? $a['block'] : '';
$action          = ! empty( $a['action'] ) ? $a['action'] : 'create';
$success_message = ! empty( $a['successMessage'] ) ? $a['successMessage'] : 'Submitted successfully.';

$context = array(
	'block'      => $block_name,
	'action'     => $action,
	'errors'     => new stdClass(),
	'formError'  => '',
	'submitting' => false,
	'submitted'  => false,
);
?>
<form
	data-wp-interactive="bsui/form"
	data-wp-context='<?php echo esc_attr( wp_json_encode( $context ) ); ?>'
	data-wp-on--submit="actions.handleSubmit"
	data-wp-on--reset="actions.handleReset"
	data-wp-class--bs-ui-submitting="state.isSubmitting"
	data-wp-class--bs-ui-submitted="state.isSubmitted"
	data-wp-init="callbacks.initForm"
	data-wp-watch="callbacks.syncErrors"
	data-bsui-form-root
	method="post"
	novalidate
>
	<div data-wp-bind--hidden="state.isSubmitted">
		<InnerBlocks />
		<div
			role="alert"
			data-wp-bind--hidden="!state.hasFormError"
			data-wp-text="context.formError"
			class="bs-ui-form-error"
			hidden
		></div>
	</div>
	<div
		data-wp-bind--hidden="!state.isSubmitted"
		class="bs-ui-form-success"
		hidden
	><?php echo esc_html( $success_message ); ?></div>
</form>
