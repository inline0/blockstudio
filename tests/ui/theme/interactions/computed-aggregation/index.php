<?php
$products = array(
	array(
		'id'       => 'p1',
		'name'     => 'Widget Alpha',
		'quantity' => 2,
		'price'    => 25.00,
		'subtotal' => 50.00,
	),
	array(
		'id'       => 'p2',
		'name'     => 'Widget Beta',
		'quantity' => 1,
		'price'    => 50.00,
		'subtotal' => 50.00,
	),
	array(
		'id'       => 'p3',
		'name'     => 'Widget Gamma',
		'quantity' => 3,
		'price'    => 10.00,
		'subtotal' => 30.00,
	),
);

$total_items = 0;
$total_value = 0;
foreach ( $products as $p ) {
	$total_items += $p['quantity'];
	$total_value += $p['quantity'] * $p['price'];
}
$average = count( $products ) > 0 ? round( $total_value / $total_items, 2 ) : 0;

wp_interactivity_state(
	'interaction/computed-aggregation',
	array(
		'products'     => $products,
		'totalItems'   => $total_items,
		'totalValue'   => $total_value,
		'averagePrice' => $average,
	)
);
?>
<div
	data-wp-interactive="interaction/computed-aggregation"
	data-interaction-computed-aggregation
	style="max-width: 500px; font-family: system-ui, sans-serif;"
>
	<h2>Computed Aggregation</h2>

	<table data-testid="product-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
		<thead>
			<tr style="border-bottom: 2px solid #e5e7eb;">
				<th style="text-align: left; padding: 8px 12px; font-size: 13px; color: #6b7280; font-weight: 600;">Product</th>
				<th style="text-align: center; padding: 8px 12px; font-size: 13px; color: #6b7280; font-weight: 600;">Qty</th>
				<th style="text-align: right; padding: 8px 12px; font-size: 13px; color: #6b7280; font-weight: 600;">Price</th>
				<th style="text-align: right; padding: 8px 12px; font-size: 13px; color: #6b7280; font-weight: 600;">Subtotal</th>
			</tr>
		</thead>
		<tbody>
			<template data-wp-each--product="state.products" data-wp-each-key="context.product.id">
				<tr data-testid="product-row" style="border-bottom: 1px solid #f3f4f6;">
					<td data-wp-text="context.product.name" style="padding: 10px 12px; font-size: 14px;"></td>
					<td style="padding: 10px 12px; text-align: center;">
						<div style="display: inline-flex; align-items: center; gap: 6px;">
							<button
								data-wp-on--click="actions.decrementQty"
								data-testid="qty-decrement"
								style="width: 28px; height: 28px; border: 1px solid #d1d5db; border-radius: 4px; background: white; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center;"
							>&minus;</button>
							<span
								data-wp-text="context.product.quantity"
								data-testid="qty-value"
								style="min-width: 24px; text-align: center; font-size: 14px;"
							></span>
							<button
								data-wp-on--click="actions.incrementQty"
								data-testid="qty-increment"
								style="width: 28px; height: 28px; border: 1px solid #d1d5db; border-radius: 4px; background: white; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center;"
							>+</button>
						</div>
					</td>
					<td style="padding: 10px 12px; text-align: right; font-size: 14px;">
						$<span data-wp-text="context.product.price"></span>
					</td>
					<td style="padding: 10px 12px; text-align: right; font-size: 14px; font-weight: 500;">
						$<span data-wp-text="context.product.subtotal" data-testid="row-subtotal"></span>
					</td>
				</tr>
			</template>
		</tbody>
	</table>

	<div style="display: flex; gap: 12px; flex-wrap: wrap;">
		<div data-testid="badge-total-items" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 9999px; font-size: 13px; color: #1d4ed8;">
			Total Items: <span data-wp-text="state.totalItems"></span>
		</div>
		<div data-testid="badge-total-value" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 9999px; font-size: 13px; color: #166534;">
			Total Value: $<span data-wp-text="state.totalValue"></span>
		</div>
		<div data-testid="badge-average-price" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #fefce8; border: 1px solid #fde68a; border-radius: 9999px; font-size: 13px; color: #854d0e;">
			Avg Price: $<span data-wp-text="state.averagePrice"></span>
		</div>
	</div>
</div>
