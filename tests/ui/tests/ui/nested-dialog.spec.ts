import { test, expect } from '@playwright/test';

const PAGE = '/nested-dialog-test/';

test.describe( 'bsui/dialog nested', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'opens outer dialog', async ( { page } ) => {
		const outerTrigger = page
			.locator( '[data-bsui-dialog-trigger]' )
			.first();
		await outerTrigger.locator( 'button' ).click();

		const dialogs = page.locator( '[role="dialog"]:not([hidden])' );
		await expect( dialogs ).toHaveCount( 1 );
		await expect( dialogs.first() ).toContainText( 'Settings' );
	} );

	test( 'opens inner dialog from within outer', async ( { page } ) => {
		const outerTrigger = page
			.locator( '[data-bsui-dialog-trigger]' )
			.first();
		await outerTrigger.locator( 'button' ).click();

		const outerDialog = page
			.locator( '[role="dialog"]:not([hidden])' )
			.first();
		await expect( outerDialog ).toBeVisible();

		const innerTrigger = outerDialog.locator(
			'[data-bsui-dialog-trigger]'
		);
		await innerTrigger.locator( 'button' ).click();

		const visibleDialogs = page.locator(
			'[role="dialog"]:not([hidden])'
		);
		await expect( visibleDialogs ).toHaveCount( 2 );
	} );

	test( 'Escape closes only innermost dialog', async ( { page } ) => {
		const outerTrigger = page
			.locator( '[data-bsui-dialog-trigger]' )
			.first();
		await outerTrigger.locator( 'button' ).click();

		const outerDialog = page
			.locator( '[role="dialog"]:not([hidden])' )
			.first();
		const innerTrigger = outerDialog.locator(
			'[data-bsui-dialog-trigger]'
		);
		await innerTrigger.locator( 'button' ).click();

		await expect(
			page.locator( '[role="dialog"]:not([hidden])' )
		).toHaveCount( 2 );

		await page.keyboard.press( 'Escape' );

		await expect(
			page.locator( '[role="dialog"]:not([hidden])' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '[role="dialog"]:not([hidden])' ).first()
		).toContainText( 'Settings' );
	} );

	test( 'scroll restored only when all dialogs closed', async ( {
		page,
	} ) => {
		const outerTrigger = page
			.locator( '[data-bsui-dialog-trigger]' )
			.first();
		await outerTrigger.locator( 'button' ).click();

		const outerDialog = page
			.locator( '[role="dialog"]:not([hidden])' )
			.first();
		const innerTrigger = outerDialog.locator(
			'[data-bsui-dialog-trigger]'
		);
		await innerTrigger.locator( 'button' ).click();

		await page.keyboard.press( 'Escape' );

		const overflowAfterInner = await page.evaluate(
			() => document.body.style.overflow
		);
		expect( overflowAfterInner ).toBe( 'hidden' );

		const closeSettings = page
			.locator( '[role="dialog"]:not([hidden])' )
			.first()
			.locator( 'button' )
			.filter( { hasText: 'Close Settings' } );
		await closeSettings.click();
		await page.waitForTimeout( 300 );

		const overflowAfterAll = await page.evaluate(
			() => document.body.style.overflow
		);
		expect( overflowAfterAll ).toBe( '' );
	} );
} );
