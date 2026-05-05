<?php
wp_interactivity_state( 'interaction/form-submission-lifecycle', array() );
?>
<div
	data-wp-interactive="interaction/form-submission-lifecycle"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'submitting'    => false,
		'submitted'     => false,
		'nameValue'     => '',
		'emailValue'    => '',
		'statusMessage' => '',
	) ) ); ?>'
	data-interaction-form-submission-lifecycle
	style="max-width: 400px; padding: 2rem;"
>
	<form
		data-wp-on--submit="interaction/form-submission-lifecycle::actions.handleSubmit"
		data-wp-class--bs-ui-submitting="context.submitting"
		data-testid="form"
	>
		<div style="display: flex; flex-direction: column; gap: 1rem;">
			<div>
				<label for="fsl-name">Name</label>
				<input
					id="fsl-name"
					type="text"
					name="name"
					data-wp-on--input="interaction/form-submission-lifecycle::actions.setName"
					data-wp-bind--value="context.nameValue"
					placeholder="Enter your name"
					data-testid="name-input"
				/>
			</div>
			<div>
				<label for="fsl-email">Email</label>
				<input
					id="fsl-email"
					type="email"
					name="email"
					data-wp-on--input="interaction/form-submission-lifecycle::actions.setEmail"
					data-wp-bind--value="context.emailValue"
					placeholder="Enter your email"
					data-testid="email-input"
				/>
			</div>
			<button
				type="submit"
				data-wp-bind--disabled="context.submitting"
				data-testid="submit-button"
				style="pointer-events: auto;"
				data-wp-style--pointer-events="context.submitting ? 'none' : 'auto'"
			>
				<span data-wp-bind--hidden="context.submitting">Submit</span>
				<span data-wp-bind--hidden="!context.submitting">Submitting...</span>
			</button>
		</div>
	</form>
	<div
		data-testid="status-message"
		data-wp-text="context.statusMessage"
		data-wp-bind--hidden="!context.submitted"
	></div>
</div>
