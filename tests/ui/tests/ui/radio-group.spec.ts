import { test, expect } from '@playwright/test';

test.describe( 'bsui/radio-group', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/radio-test/' );
		await page.waitForSelector( '[role="radiogroup"]' );
	} );

	test( 'renders radiogroup', async ( { page } ) => {
		await expect( page.locator( '[role="radiogroup"]' ) ).toHaveCount( 1 );
	} );

	test( 'renders radio buttons', async ( { page } ) => {
		await expect( page.locator( '[role="radio"]' ) ).toHaveCount( 3 );
	} );

	test( 'default selected has aria-checked=true', async ( { page } ) => {
		const radio1 = page.locator( '[role="radio"]' ).first();
		await expect( radio1 ).toHaveAttribute( 'aria-checked', 'true' );
	} );

	test( 'click selects radio', async ( { page } ) => {
		const radio2 = page.locator( '[role="radio"]' ).nth( 1 );
		await radio2.click();
		await expect( radio2 ).toHaveAttribute( 'aria-checked', 'true' );

		const radio1 = page.locator( '[role="radio"]' ).first();
		await expect( radio1 ).toHaveAttribute( 'aria-checked', 'false' );
	} );

	test( 'ArrowDown selects next', async ( { page } ) => {
		const radios = page.locator( '[role="radio"]' );
		await radios.first().focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( radios.nth( 1 ) ).toBeFocused();
		await expect( radios.nth( 1 ) ).toHaveAttribute( 'aria-checked', 'true' );
	} );

	test( 'ArrowUp selects previous', async ( { page } ) => {
		const radios = page.locator( '[role="radio"]' );
		await radios.nth( 1 ).click();
		await expect( radios.nth( 1 ) ).toHaveAttribute( 'aria-checked', 'true' );
		await page.keyboard.press( 'ArrowUp' );
		await expect( radios.first() ).toBeFocused();
		await expect( radios.first() ).toHaveAttribute( 'aria-checked', 'true' );
	} );

	test( 'has hidden form input', async ( { page } ) => {
		const input = page.locator( 'input[name="choice"]' );
		await expect( input ).toHaveAttribute( 'value', 'opt1' );
	} );

	test( 'form input updates on selection', async ( { page } ) => {
		const radio2 = page.locator( '[role="radio"]' ).nth( 1 );
		await radio2.click();
		const input = page.locator( 'input[name="choice"]' );
		await expect( input ).toHaveAttribute( 'value', 'opt2' );
	} );
} );
