import { test, expect } from '@playwright/test';

test.describe( 'bsui/toolbar', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/toolbar-test/' );
		await page.waitForSelector( '[role="toolbar"]' );
	} );

	test( 'renders toolbar', async ( { page } ) => {
		await expect( page.locator( '[role="toolbar"]' ) ).toBeVisible();
	} );

	test( 'has role=toolbar', async ( { page } ) => {
		await expect( page.locator( '[role="toolbar"]' ) ).toHaveAttribute( 'role', 'toolbar' );
	} );

	test( 'has aria-orientation', async ( { page } ) => {
		await expect( page.locator( '[role="toolbar"]' ) ).toHaveAttribute( 'aria-orientation', 'horizontal' );
	} );

	test( 'renders buttons', async ( { page } ) => {
		const buttons = page.locator( '[role="toolbar"] button' );
		await expect( buttons ).toHaveCount( 6 );
	} );

	test( 'ArrowRight moves focus', async ( { page } ) => {
		const buttons = page.locator( '[role="toolbar"] button' );
		await buttons.first().focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( buttons.nth( 1 ) ).toBeFocused();
	} );

	test( 'Home moves to first', async ( { page } ) => {
		const buttons = page.locator( '[role="toolbar"] button' );
		await buttons.nth( 2 ).focus();
		await page.keyboard.press( 'Home' );
		await expect( buttons.first() ).toBeFocused();
	} );

	test( 'End moves to last', async ( { page } ) => {
		const buttons = page.locator( '[role="toolbar"] button' );
		await buttons.first().focus();
		await page.keyboard.press( 'End' );
		await expect( buttons.last() ).toBeFocused();
	} );
} );
