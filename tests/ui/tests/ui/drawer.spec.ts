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
		await expect( root.locator( '[role="dialog"]' ) ).toBeVisible();
	} );

	test( 'Escape closes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await root.locator( '[data-bsui-drawer-trigger] button' ).click();
		await expect( root.locator( '[role="dialog"]' ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( root.locator( '[role="dialog"]' ) ).toBeHidden();
	} );

	test( 'close button closes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-drawer-root]' );
		await root.locator( '[data-bsui-drawer-trigger] button' ).click();
		const popup = root.locator( '[role="dialog"]' );
		await expect( popup ).toBeVisible();
		await popup.locator( 'button' ).click();
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
