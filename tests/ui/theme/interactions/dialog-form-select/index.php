<?php
wp_interactivity_state( 'interaction/dialog-form-select', array() );
?>
<div
	data-wp-interactive="interaction/dialog-form-select"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'dialogOpen'    => false,
		'itemName'      => '',
		'itemCategory'  => '',
		'items'         => array(
			array( 'name' => 'Landing Page', 'category' => 'Design' ),
			array( 'name' => 'API Docs', 'category' => 'Dev' ),
		),
		'selectOpen'    => false,
	) ) ); ?>'
	data-interaction-dialog-form-select
	style="max-width: 600px; padding: 2rem;"
>
	<button
		data-wp-on--click="interaction/dialog-form-select::actions.openDialog"
		data-testid="add-item-button"
	>Add Item</button>

	<div
		data-testid="dialog-backdrop"
		data-wp-bind--hidden="!context.dialogOpen"
		style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100;"
		data-wp-on--click="interaction/dialog-form-select::actions.closeDialog"
	></div>

	<div
		role="dialog"
		aria-label="Add Item"
		data-testid="dialog"
		data-wp-bind--hidden="!context.dialogOpen"
		style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; z-index: 101; min-width: 320px;"
	>
		<h3 style="margin: 0 0 1rem;">Add New Item</h3>
		<form
			data-wp-on--submit="interaction/dialog-form-select::actions.submitItem"
			data-testid="dialog-form"
			style="display: flex; flex-direction: column; gap: 1rem;"
		>
			<div>
				<label for="dfs-name">Name</label>
				<input
					id="dfs-name"
					type="text"
					data-wp-on--input="interaction/dialog-form-select::actions.setItemName"
					data-wp-bind--value="context.itemName"
					placeholder="Item name"
					data-testid="item-name-input"
				/>
			</div>
			<div>
				<label>Category</label>
				<div style="position: relative;">
					<button
						type="button"
						data-wp-on--click="interaction/dialog-form-select::actions.toggleSelect"
						data-testid="category-trigger"
						style="width: 100%; text-align: left; padding: 0.5rem; border: 1px solid #ccc; background: white; cursor: pointer;"
					>
						<span data-wp-bind--hidden="context.itemCategory" data-testid="select-placeholder">Select category</span>
						<span data-wp-bind--hidden="!context.itemCategory" data-wp-text="context.itemCategory" data-testid="select-value"></span>
					</button>
					<div
						data-wp-bind--hidden="!context.selectOpen"
						data-testid="category-popup"
						style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-radius: 4px; z-index: 10;"
					>
						<div
							role="option"
							data-wp-on--click="interaction/dialog-form-select::actions.selectCategory"
							data-value="Design"
							data-testid="option-design"
							style="padding: 0.5rem; cursor: pointer;"
						>Design</div>
						<div
							role="option"
							data-wp-on--click="interaction/dialog-form-select::actions.selectCategory"
							data-value="Dev"
							data-testid="option-dev"
							style="padding: 0.5rem; cursor: pointer;"
						>Dev</div>
						<div
							role="option"
							data-wp-on--click="interaction/dialog-form-select::actions.selectCategory"
							data-value="Marketing"
							data-testid="option-marketing"
							style="padding: 0.5rem; cursor: pointer;"
						>Marketing</div>
					</div>
				</div>
			</div>
			<button type="submit" data-testid="dialog-submit">Add</button>
		</form>
	</div>

	<h3 style="margin: 1.5rem 0 0.5rem;">Items</h3>
	<ul data-testid="item-list">
		<template data-wp-each="context.items">
			<li data-testid="item-row" style="display: flex; gap: 0.5rem; align-items: center; padding: 0.25rem 0;">
				<span data-wp-text="context.item.name" data-testid="item-name"></span>
				<span
					data-wp-text="context.item.category"
					data-testid="item-category-badge"
					style="background: #e5e7eb; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem;"
				></span>
			</li>
		</template>
	</ul>
</div>
