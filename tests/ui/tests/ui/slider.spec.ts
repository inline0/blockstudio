import { test, expect } from '@playwright/test';

test.describe( 'bsui/slider', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/slider-test/' );
		await page.waitForSelector( '[role="slider"]', { state: 'attached' } );
	} );

	test( 'renders sliders', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' );
		expect( await slider.count() ).toBe( 2 );
	} );

	test( 'has correct ARIA attributes', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await expect( slider ).toHaveAttribute( 'aria-valuemin', '0' );
		await expect( slider ).toHaveAttribute( 'aria-valuemax', '100' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '50' );
	} );

	test( 'ArrowRight increases value', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '51' );
	} );

	test( 'ArrowLeft decreases value', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'ArrowLeft' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '49' );
	} );

	test( 'Home sets to min', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'Home' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	test( 'End sets to max', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'End' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '100' );
	} );

	test( 'has hidden form input', async ( { page } ) => {
		const input = page.locator( 'input[name="volume"]' );
		await expect( input ).toHaveAttribute( 'value', '50' );
	} );

	test( 'ArrowUp increases value', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'ArrowUp' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '51' );
	} );

	test( 'ArrowDown decreases value', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '49' );
	} );

	test( 'value does not go below min', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'Home' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '0' );
		await page.keyboard.press( 'ArrowLeft' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	test( 'value does not go above max', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'End' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '100' );
		await page.keyboard.press( 'ArrowRight' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '100' );
	} );

	test( 'aria-valuenow updates after multiple interactions', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).first();
		await slider.focus();
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '53' );
		await page.keyboard.press( 'ArrowLeft' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '52' );
	} );

	test( 'disabled slider prevents keyboard interaction', async ( { page } ) => {
		const slider = page.locator( '[role="slider"]' ).nth( 1 );
		await expect( slider ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '25' );
		await slider.focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '25' );
		await page.keyboard.press( 'Home' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '25' );
		await page.keyboard.press( 'End' );
		await expect( slider ).toHaveAttribute( 'aria-valuenow', '25' );
	} );
} );
