import { test, expect } from '@playwright/test';

test.describe( 'bsui/number-field', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/number-field-test/' );
		await page.waitForSelector( '[role="spinbutton"]' );
	} );

	test( 'renders spinbutton', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await expect( input ).toBeVisible();
	} );

	test( 'has initial value', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await expect( input ).toHaveAttribute( 'aria-valuenow', '5' );
	} );

	test( 'increment button increases value', async ( { page } ) => {
		const root = page.locator( '[role="group"]' ).first();
		const inc = root.locator( 'button[aria-label="Increase"]' );
		await inc.click();
		const input = root.locator( '[role="spinbutton"]' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '6' );
	} );

	test( 'decrement button decreases value', async ( { page } ) => {
		const root = page.locator( '[role="group"]' ).first();
		const dec = root.locator( 'button[aria-label="Decrease"]' );
		await dec.click();
		const input = root.locator( '[role="spinbutton"]' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '4' );
	} );

	test( 'respects max', async ( { page } ) => {
		const root = page.locator( '[role="group"]' ).first();
		const inc = root.locator( 'button[aria-label="Increase"]' );
		for ( let i = 0; i < 20; i++ ) await inc.click();
		const input = root.locator( '[role="spinbutton"]' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '10' );
	} );

	test( 'respects min', async ( { page } ) => {
		const root = page.locator( '[role="group"]' ).first();
		const dec = root.locator( 'button[aria-label="Decrease"]' );
		for ( let i = 0; i < 20; i++ ) await dec.click();
		const input = root.locator( '[role="spinbutton"]' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	test( 'typing in input updates value', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await input.fill( '7' );
		await input.dispatchEvent( 'change' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '7' );
	} );

	test( 'ArrowUp increments value', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await page.keyboard.press( 'ArrowUp' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '6' );
	} );

	test( 'ArrowDown decrements value', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '4' );
	} );

	test( 'Shift+ArrowUp increments by 10x step', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await page.keyboard.press( 'Shift+ArrowUp' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '10' );
	} );

	test( 'Shift+ArrowDown decrements by 10x step', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await page.keyboard.press( 'Shift+ArrowDown' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	test( 'ArrowUp clamps at max', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		for ( let i = 0; i < 20; i++ ) await page.keyboard.press( 'ArrowUp' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '10' );
	} );

	test( 'ArrowDown clamps at min', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		for ( let i = 0; i < 20; i++ ) await page.keyboard.press( 'ArrowDown' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	test( 'typing value above max clamps to max', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await input.fill( '99' );
		await input.dispatchEvent( 'change' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '10' );
	} );

	test( 'typing value below min clamps to min', async ( { page } ) => {
		const input = page.locator( '[role="spinbutton"]' ).first();
		await input.focus();
		await input.fill( '-5' );
		await input.dispatchEvent( 'change' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	test( 'disabled state prevents button interaction', async ( { page } ) => {
		const group = page.locator( '[role="group"]' ).nth( 1 );
		const input = group.locator( '[role="spinbutton"]' );
		const inc = group.locator( 'button[aria-label="Increase"]' );
		const dec = group.locator( 'button[aria-label="Decrease"]' );
		await expect( input ).toBeDisabled();
		await expect( inc ).toBeDisabled();
		await expect( dec ).toBeDisabled();
		await inc.click( { force: true } );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '3' );
		await dec.click( { force: true } );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '3' );
	} );

	test( 'disabled state prevents keyboard interaction', async ( { page } ) => {
		const group = page.locator( '[role="group"]' ).nth( 1 );
		const input = group.locator( '[role="spinbutton"]' );
		await input.focus( { force: true } );
		await page.keyboard.press( 'ArrowUp' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '3' );
		await page.keyboard.press( 'ArrowDown' );
		await expect( input ).toHaveAttribute( 'aria-valuenow', '3' );
	} );
} );
