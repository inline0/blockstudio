<?php
wp_interactivity_state(
	'interaction/confirmation-delete',
	array(
		'items'         => array(
			array(
				'id'   => 'a',
				'name' => 'Project A',
			),
			array(
				'id'   => 'b',
				'name' => 'Project B',
			),
			array(
				'id'   => 'c',
				'name' => 'Project C',
			),
		),
		'dialogOpen'    => false,
		'pendingName'   => '',
		'pendingId'     => '',
		'dialogMessage' => '',
	)
);
?>
<div
	data-wp-interactive="interaction/confirmation-delete"
	data-interaction-confirmation-delete
	style="max-width: 400px; font-family: system-ui, sans-serif;"
>
	<h2>Confirmation Delete</h2>

	<ul data-testid="project-list" style="list-style: none; padding: 0; margin: 0 0 16px 0;">
		<template data-wp-each--item="state.items" data-wp-each-key="context.item.id">
			<li
				data-testid="project-item"
				style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-bottom: 1px solid #f3f4f6;"
			>
				<span data-wp-text="context.item.name" data-testid="project-name"></span>
				<button
					data-wp-on--click="actions.requestDelete"
					data-testid="delete-btn"
					style="padding: 4px 12px; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; font-size: 13px;"
				>Delete</button>
			</li>
		</template>
	</ul>

	<!-- Confirmation Dialog -->
	<div
		data-wp-bind--hidden="!state.dialogOpen"
		data-testid="confirm-dialog"
		style="position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center;"
	>
		<div
			data-wp-on--click="actions.cancelDelete"
			style="position: absolute; inset: 0; background: rgba(0,0,0,0.4);"
		></div>
		<div style="position: relative; background: white; padding: 24px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); max-width: 360px; width: 100%;">
			<p
				data-wp-text="state.dialogMessage"
				data-testid="dialog-message"
				style="margin: 0 0 20px 0; font-size: 15px; color: #111827;"
			></p>
			<div style="display: flex; gap: 8px; justify-content: flex-end;">
				<button
					data-wp-on--click="actions.cancelDelete"
					data-testid="cancel-btn"
					style="padding: 8px 16px; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
				>Cancel</button>
				<button
					data-wp-on--click="actions.confirmDelete"
					data-testid="confirm-btn"
					style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
				>Confirm</button>
			</div>
		</div>
	</div>
</div>
