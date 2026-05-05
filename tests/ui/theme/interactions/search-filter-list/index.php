<?php
$items = array( 'Apple', 'Banana', 'Cherry', 'Date', 'Elderberry', 'Fig', 'Grape' );

wp_interactivity_state(
	'interaction/search-filter-list',
	array(
		'items'        => $items,
		'visibleItems' => $items,
		'visibleCount' => count( $items ),
		'hasResults'   => true,
	)
);
?>
<div
	data-wp-interactive="interaction/search-filter-list"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'search' => '' ) ) ); ?>'
	data-interaction-search-filter-list
	style="max-width: 400px; font-family: system-ui, sans-serif;"
>
	<h2>Search Filter List</h2>

	<div style="margin-bottom: 12px;">
		<input
			type="text"
			placeholder="Search fruits..."
			data-wp-bind--value="context.search"
			data-wp-on--input="actions.updateSearch"
			data-testid="search-input"
			style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;"
		/>
	</div>

	<div style="margin-bottom: 12px;">
		<span
			data-wp-text="state.visibleCount"
			data-testid="item-count"
			style="display: inline-block; padding: 2px 8px; background: #e5e7eb; border-radius: 9999px; font-size: 13px;"
		></span>
		<span style="font-size: 13px; color: #6b7280;"> items</span>
	</div>

	<ul data-testid="item-list" style="list-style: none; padding: 0; margin: 0;">
		<template data-wp-each--item="state.visibleItems">
			<li
				data-wp-text="context.item"
				data-testid="list-item"
				style="padding: 8px 12px; border-bottom: 1px solid #f3f4f6;"
			></li>
		</template>
	</ul>

	<div
		data-wp-bind--hidden="state.hasResults"
		data-testid="empty-state"
		style="padding: 16px; text-align: center; color: #9ca3af;"
	>
		No matching items found.
	</div>
</div>
