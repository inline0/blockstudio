import { test, expect } from '@playwright/test';

const PAGE = '/collapsible-test/';

test.describe( 'bsui/collapsible', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-collapsible-root]' );
	} );

	test( 'renders three collapsible instances', async ( { page } ) => {
		const roots = page.locator( '[data-bsui-collapsible-root]' );
		await expect( roots ).toHaveCount( 3 );
	} );

	test( 'default closed: panel is hidden', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).first();
		const panel = root.locator( '[data-wp-bind--hidden]' );
		await expect( panel ).toBeHidden();
	} );

	test( 'default open: panel is visible', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).nth( 1 );
		const panel = root.locator( '[data-wp-bind--hidden]' );
		await expect( panel ).toBeVisible();
		await expect( panel ).toContainText( 'This panel starts open.' );
	} );

	test( 'closed trigger has aria-expanded=false', async ( { page } ) => {
		const trigger = page
			.locator( '[data-bsui-collapsible-root]' )
			.first()
			.locator( '[data-bsui-collapsible-trigger]' );
		await expect( trigger ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
	} );

	test( 'open trigger has aria-expanded=true', async ( { page } ) => {
		const trigger = page
			.locator( '[data-bsui-collapsible-root]' )
			.nth( 1 )
			.locator( '[data-bsui-collapsible-trigger]' );
		await expect( trigger ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
	} );

	test( 'clicking trigger opens panel', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).first();
		const triggerWrapper = root.locator( '[data-bsui-collapsible-trigger]' );
		const trigger = triggerWrapper.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.click();
		await expect( panel ).toBeVisible();
		await expect( triggerWrapper ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
	} );

	test( 'clicking trigger again closes panel', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).first();
		const triggerWrapper = root.locator( '[data-bsui-collapsible-trigger]' );
		const trigger = triggerWrapper.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.click();
		await expect( panel ).toBeVisible();

		await trigger.click();
		await expect( panel ).toBeHidden();
		await expect( triggerWrapper ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
	} );

	test( 'clicking open-by-default trigger closes it', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).nth( 1 );
		const triggerWrapper = root.locator( '[data-bsui-collapsible-trigger]' );
		const trigger = triggerWrapper.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.click();
		await expect( panel ).toBeHidden();
		await expect( triggerWrapper ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
	} );

	test( 'Enter toggles collapsible', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).first();
		const trigger = root.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.focus();
		await page.keyboard.press( 'Enter' );
		await expect( panel ).toBeVisible();

		await page.keyboard.press( 'Enter' );
		await expect( panel ).toBeHidden();
	} );

	test( 'Space toggles collapsible', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).first();
		const trigger = root.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.focus();
		await page.keyboard.press( 'Space' );
		await expect( panel ).toBeVisible();
	} );

	test( 'disabled trigger has disabled attribute', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).nth( 2 );
		const triggerWrapper = root.locator( '[data-bsui-collapsible-trigger]' );
		await expect( triggerWrapper ).toHaveAttribute( 'disabled', '' );
	} );

	test( 'disabled collapsible does not toggle on click', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).nth( 2 );
		const triggerWrapper = root.locator( '[data-bsui-collapsible-trigger]' );
		const trigger = triggerWrapper.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.click( { force: true } );
		await expect( panel ).toBeHidden();
	} );

	test( 'rapid toggling works correctly', async ( { page } ) => {
		const root = page.locator( '[data-bsui-collapsible-root]' ).first();
		const trigger = root.locator( 'button' );
		const panel = root.locator( '[data-wp-bind--hidden]' );

		await trigger.click();
		await trigger.click();
		await trigger.click();
		await expect( panel ).toBeVisible();
	} );
} );
