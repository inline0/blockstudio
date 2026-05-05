import { test, expect } from '@playwright/test';

test.describe( 'bsui/separator', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/separator-test/' );
		await page.waitForSelector( '[role="separator"]', { state: 'attached' } );
	} );

	test( 'renders separator', async ( { page } ) => {
		const sep = page.locator( '[role="separator"]' );
		expect( await sep.count() ).toBeGreaterThan( 0 );
	} );

	test( 'has role=separator', async ( { page } ) => {
		const sep = page.locator( '[role="separator"]' ).first();
		await expect( sep ).toHaveAttribute( 'role', 'separator' );
	} );

	test( 'has default orientation', async ( { page } ) => {
		const sep = page.locator( '[role="separator"]' ).first();
		await expect( sep ).toHaveAttribute( 'aria-orientation', 'horizontal' );
	} );
} );
