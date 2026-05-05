import { test, expect } from '@playwright/test';

const PAGE = '/color-picker-test/';

test.describe( 'bsui/color-picker', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-color-picker]' );
	} );

	test( 'renders color swatch and text input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-color-picker]' );
		const swatch = root.locator( '[data-bsui-color-swatch]' );
		const textInput = root.locator( 'input[type="text"]' );

		await expect( swatch ).toBeVisible();
		await expect( textInput ).toBeVisible();
	} );

	test( 'text input shows default color value', async ( { page } ) => {
		const root = page.locator( '[data-bsui-color-picker]' );
		const textInput = root.locator( 'input[type="text"]' );
		const value = await textInput.inputValue();

		expect( value ).toMatch( /^#[0-9a-fA-F]{6}$/ );
	} );

	test( 'color input inside swatch has correct value', async ( { page } ) => {
		const root = page.locator( '[data-bsui-color-picker]' );
		const colorInput = root.locator( '[data-bsui-color-swatch] input[type="color"]' );
		const textInput = root.locator( 'input[type="text"]' );

		const textValue = await textInput.inputValue();
		await expect( colorInput ).toHaveValue( textValue );
	} );

	test( 'changing text input updates swatch color', async ( { page } ) => {
		const root = page.locator( '[data-bsui-color-picker]' );
		const textInput = root.locator( 'input[type="text"]' );
		const swatch = root.locator( '[data-bsui-color-swatch]' );

		await textInput.fill( '#ff0000' );
		await page.waitForTimeout( 200 );

		const bgColor = await swatch.evaluate(
			( el ) => getComputedStyle( el ).backgroundColor
		);
		expect( bgColor ).toContain( '255' );
	} );

	test( 'invalid hex input does not update swatch', async ( { page } ) => {
		const root = page.locator( '[data-bsui-color-picker]' );
		const textInput = root.locator( 'input[type="text"]' );
		const swatch = root.locator( '[data-bsui-color-swatch]' );

		const bgBefore = await swatch.evaluate(
			( el ) => getComputedStyle( el ).backgroundColor
		);

		await textInput.fill( 'zzz' );
		await page.waitForTimeout( 200 );

		const bgAfter = await swatch.evaluate(
			( el ) => getComputedStyle( el ).backgroundColor
		);
		expect( bgAfter ).toBe( bgBefore );
	} );

	test( 'color input (swatch click) updates text input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-color-picker]' );
		const colorInput = root.locator( '[data-bsui-color-swatch] input[type="color"]' );
		const textInput = root.locator( 'input[type="text"]' );

		await colorInput.evaluate( ( el ) => {
			const input = el as HTMLInputElement;
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				HTMLInputElement.prototype, 'value'
			)?.set;
			nativeInputValueSetter?.call( input, '#00ff00' );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		await page.waitForTimeout( 200 );

		await expect( textInput ).toHaveValue( '#00ff00' );
	} );
} );
