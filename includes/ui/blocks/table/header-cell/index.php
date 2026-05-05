<?php
$column_id = ! empty( $a['columnId'] ) ? $a['columnId'] : '';
$sortable  = $a['sortable'] ?? true;
?>
<th
	data-wp-interactive="bsui/table"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'columnId' => $column_id ) ) ); ?>'
	data-wp-bind--aria-sort="state.ariaSort"
	data-column-id="<?php echo esc_attr( $column_id ); ?>"
	aria-sort="none"
	<?php if ( $sortable ) : ?>
	data-wp-on--click="actions.sort"
	<?php endif; ?>
>
	<RichText attribute="label" tag="span" placeholder="Column" />
	<?php if ( $sortable ) : ?>
	<span data-wp-text="state.sortIcon"></span>
	<?php endif; ?>
	<InnerBlocks />
</th>
