import { test, expect } from '@playwright/test';

test.describe( 'bsui/progress', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/progress-test/' );
		await page.waitForSelector( '[role="progressbar"]', { state: 'attached' } );
	} );

	test( 'renders progressbar', async ( { page } ) => {
		const bar = page.locator( '[role="progressbar"]' );
		expect( await bar.count() ).toBe( 1 );
	} );

	test( 'has correct ARIA values', async ( { page } ) => {
		const bar = page.locator( '[role="progressbar"]' );
		await expect( bar ).toHaveAttribute( 'aria-valuenow', '60' );
		await expect( bar ).toHaveAttribute( 'aria-valuemin', '0' );
		await expect( bar ).toHaveAttribute( 'aria-valuemax', '100' );
	} );

	test( 'has aria-label', async ( { page } ) => {
		const bar = page.locator( '[role="progressbar"]' );
		await expect( bar ).toHaveAttribute( 'aria-label', 'Loading' );
	} );

	test( 'has CSS custom property', async ( { page } ) => {
		const bar = page.locator( '[role="progressbar"]' );
		const style = await bar.getAttribute( 'style' );
		expect( style ).toContain( '--progress-value: 60%' );
	} );
} );
