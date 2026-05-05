import { test, expect } from '@playwright/test';

const PAGE = '/dialog-test/';

test.describe( 'bsui/dialog', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'dialog is hidden by default', async ( { page } ) => {
		const popup = page.locator( '[role="dialog"]' );
		await expect( popup ).toBeHidden();
	} );

	test( 'trigger has aria-haspopup=dialog', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		await expect( trigger ).toHaveAttribute(
			'aria-haspopup',
			'dialog'
		);
	} );

	test( 'clicking trigger opens dialog', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();
	} );

	test( 'dialog has aria-modal=true', async ( { page } ) => {
		const popup = page.locator( '[role="dialog"]' );
		await expect( popup ).toHaveAttribute( 'aria-modal', 'true' );
	} );

	test( 'backdrop hidden attribute removed when dialog opens', async ( {
		page,
	} ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const backdrop = page.locator( '[data-bsui-dialog-backdrop]' );

		await expect( backdrop ).toHaveAttribute( 'hidden', '' );

		await triggerButton.click();
		await expect( backdrop ).not.toHaveAttribute( 'hidden' );
	} );

	test( 'Escape closes dialog', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();
	} );

	test( 'focus returns to trigger on Escape', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );

		await triggerButton.click();
		await page.keyboard.press( 'Escape' );
		await expect( triggerButton ).toBeFocused();
	} );

	test( 'close button closes dialog', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		const closeBtn = popup.locator( 'button' );
		await closeBtn.click();
		await expect( popup ).toBeHidden();
	} );

	test( 'clicking backdrop closes dialog', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );
		const backdrop = page.locator( '[data-bsui-dialog-backdrop]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		await backdrop.click( { force: true } );
		await expect( popup ).toBeHidden();
	} );

	test( 'scroll is locked when dialog is open', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );

		await triggerButton.click();

		const overflow = await page.evaluate(
			() => document.body.style.overflow
		);
		expect( overflow ).toBe( 'hidden' );
	} );

	test( 'scroll is restored when dialog closes via close button', async ( {
		page,
	} ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		const closeBtn = popup.locator( 'button' );
		await closeBtn.click();
		await page.waitForTimeout( 300 );
		await expect( popup ).toBeHidden();

		const overflow = await page.evaluate(
			() => document.body.style.overflow
		);
		expect( overflow ).toBe( '' );
	} );

	test( 'dialog content renders', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toContainText( 'Dialog content here.' );
	} );

	test( 'focus trap: Tab wraps within dialog', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-dialog-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = page.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		const closeBtn = popup.locator( 'button' );
		await closeBtn.focus();

		await page.keyboard.press( 'Tab' );

		const focusedInDialog = await page.evaluate( () => {
			const el = document.activeElement;
			return !! el?.closest( '[role="dialog"]' );
		} );
		expect( focusedInDialog ).toBe( true );
	} );
} );
