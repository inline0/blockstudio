import { test, expect } from '@playwright/test';

const PAGE = '/table-test/';

test.describe( 'bsui/table', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-table-root]' );
	} );

	test( 'renders table with headers and rows', async ( { page } ) => {
		await expect( page.locator( 'table' ) ).toBeVisible();
		await expect( page.locator( 'th' ) ).toHaveCount( 4 );
		await expect( page.locator( 'tbody tr' ) ).toHaveCount( 3 );
	} );

	test( 'headers have aria-sort=none initially', async ( { page } ) => {
		const sortableHeaders = page.locator( 'th[data-wp-on--click]' );
		for ( const h of await sortableHeaders.all() ) {
			await expect( h ).toHaveAttribute( 'aria-sort', 'none' );
		}
	} );

	test( 'clicking header sorts ascending', async ( { page } ) => {
		const nameHeader = page.locator( 'th[data-column-id="name"]' );
		await nameHeader.click();
		await expect( nameHeader ).toHaveAttribute( 'aria-sort', 'ascending' );
	} );

	test( 'clicking same header again sorts descending', async ( { page } ) => {
		const nameHeader = page.locator( 'th[data-column-id="name"]' );
		await nameHeader.click();
		await nameHeader.click();
		await expect( nameHeader ).toHaveAttribute( 'aria-sort', 'descending' );
	} );

	test( 'clicking different header sorts that column', async ( { page } ) => {
		const roleHeader = page.locator( 'th[data-column-id="role"]' );
		await roleHeader.click();
		await expect( roleHeader ).toHaveAttribute( 'aria-sort', 'ascending' );
		const nameHeader = page.locator( 'th[data-column-id="name"]' );
		await expect( nameHeader ).toHaveAttribute( 'aria-sort', 'none' );
	} );

	test( 'cells can contain other blocks (menu dropdown)', async ( { page } ) => {
		const menuTrigger = page.locator( '[data-bsui-menu-trigger]' ).first();
		await expect( menuTrigger ).toBeVisible();
		await menuTrigger.locator( 'button' ).click();
		const menu = page.locator( '[data-bsui-menu-root]' ).first().locator( '[role="menu"]' );
		await expect( menu ).toBeVisible();
	} );

	test( 'non-sortable column has no click handler', async ( { page } ) => {
		const actionsHeader = page.locator( 'th[data-column-id="actions"]' );
		const hasClick = await actionsHeader.getAttribute( 'data-wp-on--click' );
		expect( hasClick ).toBeNull();
	} );
} );
