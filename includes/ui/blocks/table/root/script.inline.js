import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/table', {
	state: {
		get sortIcon() {
			const { sortColumn, sortDirection, columnId } = getContext();
			if ( sortColumn !== columnId ) return '';
			return sortDirection === 'asc' ? '\u2191' : '\u2193';
		},
		get isSorted() {
			const { sortColumn, columnId } = getContext();
			return sortColumn === columnId;
		},
		get ariaSort() {
			const { sortColumn, sortDirection, columnId } = getContext();
			if ( sortColumn !== columnId ) return 'none';
			return sortDirection === 'asc' ? 'ascending' : 'descending';
		},
	},
	actions: {
		sort() {
			const ctx = getContext();
			if ( ! ctx.sortable ) return;

			const { ref } = getElement();
			const columnId = ctx.columnId;

			if ( ctx.sortColumn === columnId ) {
				ctx.sortDirection =
					ctx.sortDirection === 'asc' ? 'desc' : 'asc';
			} else {
				ctx.sortColumn = columnId;
				ctx.sortDirection = 'asc';
			}

			const root = ref.closest( '[data-bsui-table-root]' );
			const table = root?.querySelector( 'table' );
			if ( ! table ) return;

			const tbody = table.querySelector( 'tbody' );
			if ( ! tbody ) return;

			const headers = [
				...table.querySelectorAll( 'th[data-column-id]' ),
			];
			const colIndex = headers.findIndex(
				( h ) => h.getAttribute( 'data-column-id' ) === columnId
			);
			if ( colIndex === -1 ) return;

			const rows = [ ...tbody.querySelectorAll( 'tr' ) ];
			const dir = ctx.sortDirection === 'asc' ? 1 : -1;

			rows.sort( ( a, b ) => {
				const aCell = a.children[ colIndex ];
				const bCell = b.children[ colIndex ];
				const aVal = aCell?.textContent?.trim() || '';
				const bVal = bCell?.textContent?.trim() || '';

				const aNum = parseFloat( aVal );
				const bNum = parseFloat( bVal );
				if ( ! isNaN( aNum ) && ! isNaN( bNum ) ) {
					return ( aNum - bNum ) * dir;
				}
				return aVal.localeCompare( bVal ) * dir;
			} );

			rows.forEach( ( row ) => tbody.appendChild( row ) );
		},
	},
} );
