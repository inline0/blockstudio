<?php
$sortable = $a['sortable'] ?? true;
$striped  = ! empty( $a['striped'] );
?>
<div
	data-wp-interactive="bsui/table"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'sortColumn'    => '',
		'sortDirection' => 'asc',
		'sortable'      => (bool) $sortable,
	) ) ); ?>'
	data-bsui-table-root
	<?php if ( $striped ) echo 'data-striped'; ?>
>
	<table>
		<InnerBlocks
			allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'bsui/table-header', 'bsui/table-body' ) ) ); ?>'
			template='<?php echo esc_attr( wp_json_encode( array(
				array( 'bsui/table-header', array() ),
				array( 'bsui/table-body', array() ),
			) ) ); ?>'
		/>
	</table>
</div>
