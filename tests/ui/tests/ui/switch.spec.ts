import { test, expect } from '@playwright/test';

test.describe( 'bsui/switch', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/switch-test/' );
		await page.waitForSelector( '[role="switch"]' );
	} );

	test( 'renders switch instances', async ( { page } ) => {
		await expect( page.locator( '[role="switch"]' ) ).toHaveCount( 4 );
	} );

	test( 'has role=switch', async ( { page } ) => {
		const sw = page.locator( '[role="switch"]' ).first();
		await expect( sw ).toHaveAttribute( 'role', 'switch' );
	} );

	test( 'default switch has aria-checked=false', async ( { page } ) => {
		const sw = page.locator( '[role="switch"]' ).first();
		await expect( sw ).toHaveAttribute( 'aria-checked', 'false' );
	} );

	test( 'checked switch has aria-checked=true', async ( { page } ) => {
		const sw = page.locator( '[role="switch"]' ).nth( 1 );
		await expect( sw ).toHaveAttribute( 'aria-checked', 'true' );
	} );

	test( 'disabled switch does not toggle', async ( { page } ) => {
		const sw = page.locator( '[role="switch"]' ).nth( 2 );
		await expect( sw ).toBeDisabled();
	} );

	test( 'switch has label text', async ( { page } ) => {
		const label = page.locator( '[data-bsui-switch]' ).first().locator( 'span' ).last();
		await expect( label ).toBeVisible();
	} );
} );
