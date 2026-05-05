import { test, expect } from '@playwright/test';

test.describe( 'bsui/checkbox', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/checkbox-test/' );
		await page.waitForSelector( '[role="checkbox"]' );
	} );

	test( 'renders checkboxes', async ( { page } ) => {
		await expect( page.locator( '[role="checkbox"]' ) ).toHaveCount( 4 );
	} );

	test( 'default unchecked', async ( { page } ) => {
		const cb = page.locator( '[role="checkbox"]' ).first();
		await expect( cb ).toHaveAttribute( 'aria-checked', 'false' );
	} );

	test( 'default checked', async ( { page } ) => {
		const cb = page.locator( '[role="checkbox"]' ).nth( 1 );
		await expect( cb ).toHaveAttribute( 'aria-checked', 'true' );
	} );

	test( 'disabled checkbox has disabled attribute', async ( { page } ) => {
		const cb = page.locator( '[role="checkbox"]' ).nth( 2 );
		await expect( cb ).toBeDisabled();
	} );

	test( 'checkbox has label text', async ( { page } ) => {
		const label = page.locator( '[data-bsui-checkbox]' ).first().locator( 'span' );
		await expect( label ).toHaveText( 'Default checkbox' );
	} );

	test( 'checkbox is wrapped in label', async ( { page } ) => {
		const wrapper = page.locator( '[data-bsui-checkbox]' ).first();
		await expect( wrapper ).toHaveAttribute( 'data-bsui-checkbox', '' );
	} );
} );
