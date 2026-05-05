import { test, expect } from '@playwright/test';

test.describe( 'bsui/alert-dialog', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/alert-dialog-test/' );
		await page.waitForSelector( '[data-bsui-alert-dialog-root]' );
	} );

	test( 'hidden by default', async ( { page } ) => {
		const popup = page.locator( '[role="alertdialog"]' );
		await expect( popup ).toBeHidden();
	} );

	test( 'no flash of popup on page load', async ( { browser } ) => {
		const context = await browser.newContext();
		const page = await context.newPage();

		const frames: Buffer[] = [];
		await page.route( '**/*', ( route ) => route.continue() );

		page.on( 'framenavigated', async () => {
			try {
				frames.push( await page.screenshot() );
			} catch {}
		} );

		await page.goto( '/alert-dialog-test/', { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 500 );

		const popup = page.locator( '[role="alertdialog"]' );
		await expect( popup ).toBeHidden();

		const box = await page.evaluate( () => {
			const el = document.querySelector( '[role="alertdialog"]' );
			if ( ! el ) return null;
			const r = el.getBoundingClientRect();
			return { width: r.width, height: r.height };
		} );
		expect( box?.width === 0 || box?.height === 0 || box === null ).toBe( true );

		await context.close();
	} );

	test( 'has role=alertdialog', async ( { page } ) => {
		const popup = page.locator( '[role="alertdialog"]' );
		await expect( popup ).toHaveAttribute( 'role', 'alertdialog' );
	} );

	test( 'click trigger opens', async ( { page } ) => {
		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		await expect( page.locator( '[role="alertdialog"]' ) ).toBeVisible();
	} );

	test( 'has aria-modal=true', async ( { page } ) => {
		await expect( page.locator( '[role="alertdialog"]' ) ).toHaveAttribute( 'aria-modal', 'true' );
	} );

	test( 'close button closes', async ( { page } ) => {
		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		const popup = page.locator( '[role="alertdialog"]' );
		await expect( popup ).toBeVisible();
		await popup.locator( 'button' ).click();
		await expect( popup ).toBeHidden();
	} );

	test( 'Escape does NOT close (non-dismissable)', async ( { page } ) => {
		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		const popup = page.locator( '[role="alertdialog"]' );
		await expect( popup ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeVisible();
	} );

	test( 'focus trap works', async ( { page } ) => {
		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		const popup = page.locator( '[role="alertdialog"]' );
		await expect( popup ).toBeVisible();

		await page.keyboard.press( 'Tab' );
		const inDialog = await page.evaluate( () =>
			!! document.activeElement?.closest( '[role="alertdialog"]' )
		);
		expect( inDialog ).toBe( true );
	} );
} );
