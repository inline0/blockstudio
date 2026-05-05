<?php
wp_interactivity_state(
	'interaction/multi-step-workflow',
	array(
		'currentStep' => 1,
		'email'       => '',
		'name'        => '',
		'error'       => '',
		'submitted'   => false,
		'stepLabel'   => 'Step 1 of 3',
	)
);
?>
<div
	data-wp-interactive="interaction/multi-step-workflow"
	data-interaction-multi-step-workflow
	style="max-width: 400px; font-family: system-ui, sans-serif;"
>
	<h2>Multi Step Workflow</h2>

	<div
		data-testid="step-indicator"
		data-wp-text="state.stepLabel"
		style="margin-bottom: 16px; font-size: 14px; color: #6b7280; font-weight: 500;"
	></div>

	<!-- Step 1: Email -->
	<div data-wp-bind--hidden="!state.isStep1" data-testid="step-1">
		<label style="display: block; margin-bottom: 4px; font-size: 14px; font-weight: 500;">Email</label>
		<input
			type="email"
			placeholder="Enter your email"
			data-wp-bind--value="state.email"
			data-wp-on--input="actions.setEmail"
			data-testid="email-input"
			style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; margin-bottom: 8px; box-sizing: border-box;"
		/>
		<div
			data-wp-bind--hidden="!state.error"
			data-wp-text="state.error"
			data-testid="error-message"
			style="color: #ef4444; font-size: 13px; margin-bottom: 8px;"
		></div>
		<button
			data-wp-on--click="actions.continueStep"
			data-testid="continue-btn"
			style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
		>Continue</button>
	</div>

	<!-- Step 2: Name -->
	<div data-wp-bind--hidden="!state.isStep2" data-testid="step-2">
		<label style="display: block; margin-bottom: 4px; font-size: 14px; font-weight: 500;">Name</label>
		<input
			type="text"
			placeholder="Enter your name"
			data-wp-bind--value="state.name"
			data-wp-on--input="actions.setName"
			data-testid="name-input"
			style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; margin-bottom: 8px; box-sizing: border-box;"
		/>
		<div
			data-wp-bind--hidden="!state.error"
			data-wp-text="state.error"
			data-testid="error-message-2"
			style="color: #ef4444; font-size: 13px; margin-bottom: 8px;"
		></div>
		<div style="display: flex; gap: 8px;">
			<button
				data-wp-on--click="actions.back"
				data-testid="back-btn"
				style="padding: 8px 16px; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
			>Back</button>
			<button
				data-wp-on--click="actions.continueStep"
				data-testid="continue-btn-2"
				style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
			>Continue</button>
		</div>
	</div>

	<!-- Step 3: Confirmation -->
	<div data-wp-bind--hidden="!state.isStep3" data-testid="step-3">
		<div data-wp-bind--hidden="state.submitted">
			<h3 style="margin: 0 0 12px 0; font-size: 16px;">Confirm your details</h3>
			<div style="margin-bottom: 8px;">
				<span style="font-size: 13px; color: #6b7280;">Email:</span>
				<span data-wp-text="state.email" data-testid="summary-email" style="font-size: 14px; margin-left: 4px;"></span>
			</div>
			<div style="margin-bottom: 16px;">
				<span style="font-size: 13px; color: #6b7280;">Name:</span>
				<span data-wp-text="state.name" data-testid="summary-name" style="font-size: 14px; margin-left: 4px;"></span>
			</div>
			<div style="display: flex; gap: 8px;">
				<button
					data-wp-on--click="actions.back"
					data-testid="back-btn-3"
					style="padding: 8px 16px; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
				>Back</button>
				<button
					data-wp-on--click="actions.submit"
					data-testid="submit-btn"
					style="padding: 8px 16px; background: #22c55e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
				>Submit</button>
			</div>
		</div>
		<div
			data-wp-bind--hidden="!state.submitted"
			data-testid="success-message"
			style="padding: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; text-align: center; color: #166534;"
		>
			Successfully submitted!
		</div>
	</div>
</div>
