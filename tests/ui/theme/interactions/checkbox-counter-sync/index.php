<?php
wp_interactivity_state( 'interaction/checkbox-counter-sync', array() );
?>
<div
	data-wp-interactive="interaction/checkbox-counter-sync"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'items' => array(
			array( 'id' => 1, 'label' => 'Write documentation', 'done' => false ),
			array( 'id' => 2, 'label' => 'Review pull request', 'done' => false ),
			array( 'id' => 3, 'label' => 'Deploy to production', 'done' => false ),
		),
	) ) ); ?>'
	data-interaction-checkbox-counter-sync
	style="max-width: 400px; padding: 2rem;"
>
	<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
		<h3 style="margin: 0;">Tasks</h3>
		<span
			data-testid="counter-badge"
			style="background: #e5e7eb; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem;"
		><span data-wp-text="state.doneCount"></span>/<span data-wp-text="state.totalCount"></span> completed</span>
	</div>

	<ul style="list-style: none; padding: 0; margin: 0 0 1rem 0;">
		<template data-wp-each="context.items" data-wp-each-key="context.item.id">
			<li data-testid="task-item" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
				<input
					type="checkbox"
					data-wp-bind--checked="context.item.done"
					data-wp-on--change="interaction/checkbox-counter-sync::actions.toggleItem"
					data-wp-bind--data-item-id="context.item.id"
					data-testid="task-checkbox"
					style="width: 1rem; height: 1rem; cursor: pointer;"
				/>
				<span
					data-wp-text="context.item.label"
					data-wp-style--text-decoration="context.item.done ? 'line-through' : 'none'"
					data-wp-style--opacity="context.item.done ? '0.5' : '1'"
					data-testid="task-label"
				></span>
			</li>
		</template>
	</ul>

	<div style="display: flex; gap: 0.5rem;">
		<button
			data-wp-on--click="interaction/checkbox-counter-sync::actions.completeAll"
			data-testid="complete-all-button"
		>Complete All</button>
		<button
			data-wp-on--click="interaction/checkbox-counter-sync::actions.resetAll"
			data-testid="reset-all-button"
		>Reset All</button>
	</div>
</div>
