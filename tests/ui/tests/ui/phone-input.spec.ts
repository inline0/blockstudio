import { test, expect } from '@playwright/test';

const PAGE = '/phone-input-test/';

test.describe( 'bsui/phone-input', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-phone-input]' );
	} );

	test( 'renders country trigger and tel input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-phone-input]' );
		const trigger = root.locator( '[data-bsui-phone-trigger]' );
		const telInput = root.locator( 'input[type="tel"]' );

		await expect( trigger ).toBeVisible();
		await expect( telInput ).toBeVisible();
	} );

	test( 'default country shows US +1', async ( { page } ) => {
		const root = page.locator( '[data-bsui-phone-input]' );
		const code = root.locator( '[data-bsui-phone-code]' );

		await expect( code ).toHaveText( 'US +1' );
	} );

	test( 'clicking trigger opens country popup', async ( { page } ) => {
		const root = page.locator( '[data-bsui-phone-input]' );
		const trigger = root.locator( '[data-bsui-phone-trigger]' );
		const popup = root.locator( '[data-bsui-phone-popup]' );

		await expect( popup ).toBeHidden();
		await trigger.click();
		await expect( popup ).toBeVisible();
	} );

	test( 'search filters countries', async ( { page } ) => {
		const root = page.locator( '[data-bsui-phone-input]' );
		const trigger = root.locator( '[data-bsui-phone-trigger]' );

		await trigger.click();
		const searchInput = root.locator( '[data-bsui-phone-search-input]' );
		await searchInput.fill( 'germany' );

		const visibleOptions = root.locator( '[data-bsui-phone-option]:not([hidden])' );
		await expect( visibleOptions ).toHaveCount( 1 );
		await expect( visibleOptions.first() ).toContainText( 'Germany' );
	} );

	test( 'selecting a country updates the trigger', async ( { page } ) => {
		const root = page.locator( '[data-bsui-phone-input]' );
		const trigger = root.locator( '[data-bsui-phone-trigger]' );
		const code = root.locator( '[data-bsui-phone-code]' );

		await trigger.click();
		const searchInput = root.locator( '[data-bsui-phone-search-input]' );
		await searchInput.fill( 'united king' );

		const option = root.locator( '[data-bsui-phone-option]:not([hidden])' ).first();
		await option.click();

		await expect( code ).toHaveText( 'GB +44' );
	} );

	test( 'has hidden input for form value', async ( { page } ) => {
		const root = page.locator( '[data-bsui-phone-input]' );
		const hiddenInput = root.locator( 'input[type="hidden"]' );

		await expect( hiddenInput ).toHaveCount( 1 );
	} );
} );
