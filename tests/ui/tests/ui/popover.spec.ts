import { test, expect } from '@playwright/test';

const PAGE = '/popover-test/';

test.describe( 'bsui/popover', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-popover-root]' );
	} );

	test( 'popup is hidden by default', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const popup = root.locator( '[role="dialog"]' );
		await expect( popup ).toBeHidden();
	} );

	test( 'trigger has aria-haspopup=dialog', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		await expect( trigger ).toHaveAttribute(
			'aria-haspopup',
			'dialog'
		);
	} );

	test( 'trigger has aria-expanded=false initially', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		await expect( trigger ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
	} );

	test( 'clicking trigger opens popover', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = root.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();
		await expect( trigger ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
	} );

	test( 'clicking trigger again closes popover', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = root.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		await triggerButton.click();
		await expect( popup ).toBeHidden();
	} );

	test( 'Escape closes popover and returns focus', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = root.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();
		await expect( triggerButton ).toBeFocused();
	} );

	test( 'clicking outside closes popover', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = root.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		await page.click( 'body', { position: { x: 10, y: 10 } } );
		await expect( popup ).toBeHidden();
	} );

	test( 'close button closes popover', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = root.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toBeVisible();

		const closeBtn = popup.locator( 'button' );
		await closeBtn.click();
		await expect( popup ).toBeHidden();
	} );

	test( 'popover content renders correctly', async ( { page } ) => {
		const root = page.locator( '[data-bsui-popover-root]' );
		const trigger = root.locator( '[data-bsui-popover-trigger]' );
		const triggerButton = trigger.locator( 'button' );
		const popup = root.locator( '[role="dialog"]' );

		await triggerButton.click();
		await expect( popup ).toContainText( 'Popover content here.' );
	} );
} );
