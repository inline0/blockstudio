import { test, expect } from '@playwright/test';

test.describe( 'bsui/drawer', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/drawer-test/' );
		await page.waitForSelector( '[data-bsui-drawer-root]' );
	} );

	test( 'hidden by default', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		const popup = root.locator( '[role="dialog"]' );
		await expect( popup ).toBeHidden();
	} );

	test( 'click trigger opens', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await root.locator( '[data-bsui-drawer-trigger] button' ).click();
		// The popup portals to the body while open, so it escapes stacking
		// contexts and is no longer a descendant of the root.
		await expect( page.locator( '[data-bsui-drawer-popup]' ) ).toBeVisible();
	} );

	test( 'Escape closes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await root.locator( '[data-bsui-drawer-trigger] button' ).click();
		const popup = page.locator( '[data-bsui-drawer-popup]' );
		await expect( popup ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();
	} );

	test( 'close button closes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await root.locator( '[data-bsui-drawer-trigger] button' ).click();
		const popup = page.locator( '[data-bsui-drawer-popup]' );
		await expect( popup ).toBeVisible();
		await popup.locator( '[data-bsui-drawer-close] button' ).click();
		await expect( popup ).toBeHidden();
	} );

	test( 'scroll locked when open', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await root.locator( '[data-bsui-drawer-trigger] button' ).click();
		const overflow = await page.evaluate( () => document.body.style.overflow );
		expect( overflow ).toBe( 'hidden' );
	} );

	test( 'has aria-modal', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await expect( root.locator( '[role="dialog"]' ) ).toHaveAttribute( 'aria-modal', 'true' );
	} );
} );
