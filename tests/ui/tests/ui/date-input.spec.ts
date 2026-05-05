import { test, expect } from '@playwright/test';

const PAGE = '/date-input-test/';

test.describe( 'bsui/date-input', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-date-input]' );
	} );

	test( 'renders text input with placeholder', async ( { page } ) => {
		const root = page.locator( '[data-bsui-date-input]' );
		const input = root.locator( 'input[type="text"]' );

		await expect( input ).toBeVisible();
		const placeholder = await input.getAttribute( 'placeholder' );
		expect( placeholder ).toBeTruthy();
	} );

	test( 'clicking input toggles calendar popup', async ( { page } ) => {
		const root = page.locator( '[data-bsui-date-input]' );
		const input = root.locator( 'input[type="text"]' );

		await input.click();
		await page.waitForTimeout( 200 );

		const popup = root.locator( '[data-bsui-date-input-popup]' );
		await expect( popup ).toBeVisible();
	} );

	test( 'calendar popup is hidden initially', async ( { page } ) => {
		const root = page.locator( '[data-bsui-date-input]' );
		const popup = root.locator( '[data-bsui-date-input-popup]' );
		await expect( popup ).toBeHidden();
	} );
} );
