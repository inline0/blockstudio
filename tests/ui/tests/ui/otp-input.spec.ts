import { test, expect } from '@playwright/test';

const PAGE = '/otp-input-test/';

test.describe( 'bsui/otp-input', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-otp-input]' );
	} );

	test( 'renders 6 input boxes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await expect( boxes ).toHaveCount( 6 );
	} );

	test( 'has separator in the middle', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const separator = root.locator( '[data-bsui-otp-separator]' );

		await expect( separator ).toHaveCount( 1 );
		await expect( separator ).toBeVisible();
	} );

	test( 'typing a digit auto-focuses next input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await boxes.first().focus();
		await page.keyboard.type( '1' );
		await page.waitForTimeout( 200 );

		await expect( boxes.nth( 1 ) ).toBeFocused();
	} );

	test( 'backspace on empty focuses previous', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await boxes.first().focus();
		await page.keyboard.type( '1' );
		await page.waitForTimeout( 200 );

		await page.keyboard.press( 'Backspace' );
		await page.waitForTimeout( 200 );

		await expect( boxes.first() ).toBeFocused();
	} );

	test( 'paste fills all boxes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await boxes.first().focus();
		await page.evaluate( () => {
			const input = document.querySelector( '[data-bsui-otp-input] input[data-index="0"]' );
			const dt = new DataTransfer();
			dt.setData( 'text/plain', '123456' );
			input.dispatchEvent( new ClipboardEvent( 'paste', { clipboardData: dt, bubbles: true, cancelable: true } ) );
		} );
		await page.waitForTimeout( 200 );

		await expect( boxes.nth( 0 ) ).toHaveValue( '1' );
		await expect( boxes.nth( 1 ) ).toHaveValue( '2' );
		await expect( boxes.nth( 2 ) ).toHaveValue( '3' );
		await expect( boxes.nth( 3 ) ).toHaveValue( '4' );
		await expect( boxes.nth( 4 ) ).toHaveValue( '5' );
		await expect( boxes.nth( 5 ) ).toHaveValue( '6' );
	} );

	test( 'hidden input aggregates all values', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );
		const hiddenInput = root.locator( 'input[type="hidden"]' );

		await boxes.first().focus();
		await page.keyboard.type( '1' );
		await page.waitForTimeout( 200 );
		await page.keyboard.type( '2' );
		await page.waitForTimeout( 200 );
		await page.keyboard.type( '3' );
		await page.waitForTimeout( 200 );

		const value = await hiddenInput.getAttribute( 'value' );
		expect( value ).toContain( '123' );
	} );

	test( 'ArrowLeft moves focus to previous input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await boxes.nth( 2 ).focus();
		await page.keyboard.press( 'ArrowLeft' );
		await page.waitForTimeout( 200 );

		await expect( boxes.nth( 1 ) ).toBeFocused();
	} );

	test( 'ArrowRight moves focus to next input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await boxes.nth( 2 ).focus();
		await page.keyboard.press( 'ArrowRight' );
		await page.waitForTimeout( 200 );

		await expect( boxes.nth( 3 ) ).toBeFocused();
	} );

	test( 'only numeric input accepted, letters rejected', async ( { page } ) => {
		const root = page.locator( '[data-bsui-otp-input]' );
		const boxes = root.locator( 'input[data-index]' );

		await boxes.first().focus();
		await page.keyboard.type( 'a' );
		await page.waitForTimeout( 200 );

		await expect( boxes.first() ).toHaveValue( '' );
		await expect( boxes.first() ).toBeFocused();
	} );
} );
