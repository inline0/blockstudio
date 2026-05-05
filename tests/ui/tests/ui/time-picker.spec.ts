import { test, expect } from '@playwright/test';

const PAGE = '/time-picker-test/';

test.describe( 'bsui/time-picker', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-time-picker]' );
	} );

	test( 'renders hour and minute inputs', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );
		const minuteInput = root.locator( '[data-bsui-time-minute]' );

		await expect( hourInput ).toBeVisible();
		await expect( minuteInput ).toBeVisible();
	} );

	test( 'default value populates inputs', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );
		const minuteInput = root.locator( '[data-bsui-time-minute]' );

		await expect( hourInput ).toHaveValue( '14' );
		await expect( minuteInput ).toHaveValue( '30' );
	} );

	test( 'typing hours auto-advances to minutes', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );
		const minuteInput = root.locator( '[data-bsui-time-minute]' );

		await hourInput.focus();
		await hourInput.selectText();
		await page.keyboard.press( 'Backspace' );
		await page.keyboard.type( '09', { delay: 50 } );

		await expect( minuteInput ).toBeFocused();
	} );

	test( 'arrow keys increment/decrement hours', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );

		await hourInput.focus();
		await page.keyboard.press( 'ArrowUp' );

		await expect( hourInput ).toHaveValue( '15' );
	} );

	test( 'has hidden input with form value', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hiddenInput = root.locator( 'input[type="hidden"]' );

		await expect( hiddenInput ).toHaveCount( 1 );
	} );

	test( 'ArrowDown decrements hours', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );

		await hourInput.focus();
		await page.keyboard.press( 'ArrowDown' );

		await expect( hourInput ).toHaveValue( '13' );
	} );

	test( 'typing in minute field updates value', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const minuteInput = root.locator( '[data-bsui-time-minute]' );

		await minuteInput.focus();
		await minuteInput.selectText();
		await page.keyboard.press( 'Backspace' );
		await page.keyboard.type( '45', { delay: 50 } );

		await expect( minuteInput ).toHaveValue( '45' );
	} );

	test( 'Backspace on empty minute field focuses hour field', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );
		const minuteInput = root.locator( '[data-bsui-time-minute]' );

		await minuteInput.focus();
		await minuteInput.selectText();
		await page.keyboard.press( 'Backspace' );
		await page.keyboard.press( 'Backspace' );

		await expect( hourInput ).toBeFocused();
	} );

	test( 'hour wraps from 23 to 0 on ArrowUp', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const hourInput = root.locator( '[data-bsui-time-hour]' );

		await hourInput.focus();
		await hourInput.selectText();
		await page.keyboard.press( 'Backspace' );
		await page.keyboard.type( '23', { delay: 50 } );

		// Re-focus hour since typing '23' auto-advances to minute
		await hourInput.focus();
		await page.keyboard.press( 'ArrowUp' );

		await expect( hourInput ).toHaveValue( '00' );
	} );

	test( 'minute wraps past 59 on ArrowUp', async ( { page } ) => {
		const root = page.locator( '[data-bsui-time-picker]' );
		const minuteInput = root.locator( '[data-bsui-time-minute]' );

		// Default is 30, step is 15. ArrowUp twice: 30 -> 45 -> 0 (wraps)
		await minuteInput.focus();
		await page.keyboard.press( 'ArrowUp' );
		await expect( minuteInput ).toHaveValue( '45' );
		await page.keyboard.press( 'ArrowUp' );
		await expect( minuteInput ).toHaveValue( '00' );
	} );
} );
