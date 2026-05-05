import { test, expect } from '@playwright/test';

test.describe( 'bsui/toggle-group', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/toggle-group-test/' );
		await page.waitForSelector( '[data-bsui-toggle-group-root]' );
	} );

	test( 'renders group with items', async ( { page } ) => {
		const items = page.locator( '[data-bsui-toggle-group-item]' );
		await expect( items ).toHaveCount( 3 );
	} );

	test( 'has role=group', async ( { page } ) => {
		const root = page.locator( '[data-bsui-toggle-group-root]' );
		await expect( root ).toHaveAttribute( 'role', 'group' );
	} );

	test( 'click toggles item', async ( { page } ) => {
		const item = page.locator( '[data-bsui-toggle-group-item]' ).nth( 1 );
		await item.click();
		await expect( item ).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	test( 'single mode: selecting new deselects previous', async ( { page } ) => {
		const items = page.locator( '[data-bsui-toggle-group-item]' );
		await items.nth( 1 ).click();
		await expect( items.nth( 1 ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( items.first() ).toHaveAttribute( 'aria-pressed', 'false' );
	} );

	test( 'ArrowRight moves focus', async ( { page } ) => {
		const items = page.locator( '[data-bsui-toggle-group-item]' );
		await items.first().focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( items.nth( 1 ) ).toBeFocused();
	} );
} );
