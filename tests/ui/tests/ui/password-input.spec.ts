import { test, expect } from '@playwright/test';

const PAGE = '/password-input-test/';

test.describe( 'bsui/password-input', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-password-input]' );
	} );

	test( 'renders with type=password', async ( { page } ) => {
		const root = page.locator( '[data-bsui-password-input]' );
		const input = root.locator( 'input' );

		await expect( input ).toHaveAttribute( 'type', 'password' );
	} );

	test( 'clicking toggle shows password', async ( { page } ) => {
		const root = page.locator( '[data-bsui-password-input]' );
		const input = root.locator( 'input' );
		const toggle = root.locator( 'button[aria-label="Toggle password visibility"]' );

		await toggle.click();
		await page.waitForTimeout( 200 );

		await expect( input ).toHaveAttribute( 'type', 'text' );
	} );

	test( 'clicking toggle again hides password', async ( { page } ) => {
		const root = page.locator( '[data-bsui-password-input]' );
		const input = root.locator( 'input' );
		const toggle = root.locator( 'button[aria-label="Toggle password visibility"]' );

		await toggle.click();
		await page.waitForTimeout( 200 );
		await expect( input ).toHaveAttribute( 'type', 'text' );

		await toggle.click();
		await page.waitForTimeout( 200 );
		await expect( input ).toHaveAttribute( 'type', 'password' );
	} );

	test( 'typing in password field works', async ( { page } ) => {
		const root = page.locator( '[data-bsui-password-input]' );
		const input = root.locator( 'input' );

		await input.fill( 'my-secret-123' );

		await expect( input ).toHaveValue( 'my-secret-123' );
	} );

	test( 'toggle button has correct aria-label', async ( { page } ) => {
		const root = page.locator( '[data-bsui-password-input]' );
		const toggle = root.locator( 'button' );

		await expect( toggle ).toHaveAttribute( 'aria-label', 'Toggle password visibility' );
	} );

	test( 'input type attribute changes between password/text on toggle', async ( { page } ) => {
		const root = page.locator( '[data-bsui-password-input]' );
		const input = root.locator( 'input' );
		const toggle = root.locator( 'button[aria-label="Toggle password visibility"]' );

		await expect( input ).toHaveAttribute( 'type', 'password' );

		await toggle.click();
		await page.waitForTimeout( 200 );
		await expect( input ).toHaveAttribute( 'type', 'text' );

		await toggle.click();
		await page.waitForTimeout( 200 );
		await expect( input ).toHaveAttribute( 'type', 'password' );

		await toggle.click();
		await page.waitForTimeout( 200 );
		await expect( input ).toHaveAttribute( 'type', 'text' );
	} );
} );
