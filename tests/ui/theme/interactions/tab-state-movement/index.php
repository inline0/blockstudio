<?php
wp_interactivity_state( 'interaction/tab-state-movement', array() );
?>
<div
	data-wp-interactive="interaction/tab-state-movement"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'activeTab' => 'active',
		'items'     => array(
			array( 'id' => 1, 'name' => 'Homepage Redesign', 'archived' => false ),
			array( 'id' => 2, 'name' => 'API Integration', 'archived' => false ),
			array( 'id' => 3, 'name' => 'Old Dashboard', 'archived' => true ),
		),
	) ) ); ?>'
	data-interaction-tab-state-movement
	style="max-width: 600px; padding: 2rem;"
>
	<div role="tablist" aria-orientation="horizontal" style="display: flex; gap: 0; border-bottom: 2px solid #e5e7eb;">
		<button
			role="tab"
			data-wp-on--click="interaction/tab-state-movement::actions.showActive"
			data-wp-bind--aria-selected="context.activeTab === 'active'"
			data-wp-style--border-bottom-color="context.activeTab === 'active' ? '#3b82f6' : 'transparent'"
			data-testid="tab-active"
			style="padding: 0.5rem 1rem; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; background: none; cursor: pointer;"
		>
			Active
			<span
				data-wp-text="state.activeCount"
				data-testid="active-count"
				style="margin-left: 0.25rem; background: #e5e7eb; padding: 0 0.4rem; border-radius: 9999px; font-size: 0.75rem;"
			></span>
		</button>
		<button
			role="tab"
			data-wp-on--click="interaction/tab-state-movement::actions.showArchived"
			data-wp-bind--aria-selected="context.activeTab === 'archived'"
			data-wp-style--border-bottom-color="context.activeTab === 'archived' ? '#3b82f6' : 'transparent'"
			data-testid="tab-archived"
			style="padding: 0.5rem 1rem; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; background: none; cursor: pointer;"
		>
			Archived
			<span
				data-wp-text="state.archivedCount"
				data-testid="archived-count"
				style="margin-left: 0.25rem; background: #e5e7eb; padding: 0 0.4rem; border-radius: 9999px; font-size: 0.75rem;"
			></span>
		</button>
	</div>

	<div
		role="tabpanel"
		data-wp-bind--hidden="context.activeTab !== 'active'"
		data-testid="panel-active"
		style="padding: 1rem 0;"
	>
		<template data-wp-each="state.activeItems" data-wp-each-key="context.item.id">
			<div data-testid="active-item" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
				<span data-wp-text="context.item.name" data-testid="item-name"></span>
				<button
					role="switch"
					data-wp-bind--aria-checked="!context.item.archived"
					data-wp-on--click="interaction/tab-state-movement::actions.toggleArchived"
					data-wp-bind--data-item-id="context.item.id"
					data-testid="item-switch"
					style="padding: 0.25rem 0.75rem; border-radius: 4px; border: 1px solid #ccc; background: #d1fae5; cursor: pointer; font-size: 0.75rem;"
				>Active</button>
			</div>
		</template>
		<div data-wp-bind--hidden="state.activeCount > 0" data-testid="active-empty" style="padding: 1rem 0; color: #9ca3af;">No active items.</div>
	</div>

	<div
		role="tabpanel"
		data-wp-bind--hidden="context.activeTab !== 'archived'"
		data-testid="panel-archived"
		style="padding: 1rem 0;"
	>
		<template data-wp-each="state.archivedItems" data-wp-each-key="context.item.id">
			<div data-testid="archived-item" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
				<span data-wp-text="context.item.name" data-testid="item-name"></span>
				<button
					role="switch"
					data-wp-bind--aria-checked="!context.item.archived"
					data-wp-on--click="interaction/tab-state-movement::actions.toggleArchived"
					data-wp-bind--data-item-id="context.item.id"
					data-testid="item-switch"
					style="padding: 0.25rem 0.75rem; border-radius: 4px; border: 1px solid #ccc; background: #fee2e2; cursor: pointer; font-size: 0.75rem;"
				>Archived</button>
			</div>
		</template>
		<div data-wp-bind--hidden="state.archivedCount > 0" data-testid="archived-empty" style="padding: 1rem 0; color: #9ca3af;">No archived items.</div>
	</div>
</div>
