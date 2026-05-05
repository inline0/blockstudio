import { test, expect } from '@playwright/test';

const PAGE = '/tooltip-test/';

test.describe( 'bsui/tooltip', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-tooltip-root]' );
	} );

	test( 'tooltip popup is hidden by default', async ( { page } ) => {
		const popup = page.locator( '[role="tooltip"]' ).first();
		await expect( popup ).toBeHidden();
	} );

	test( 'popup has role=tooltip', async ( { page } ) => {
		const popup = page.locator( '[role="tooltip"]' ).first();
		await expect( popup ).toHaveAttribute( 'role', 'tooltip' );
	} );

	test( 'focus shows tooltip', async ( { page } ) => {
		const trigger = page
			.locator( '[data-bsui-tooltip-root]' )
			.first()
			.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).first();

		await trigger.focus();
		await expect( popup ).toBeVisible();
	} );

	test( 'blur hides tooltip', async ( { page } ) => {
		const trigger = page
			.locator( '[data-bsui-tooltip-root]' )
			.first()
			.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).first();

		await trigger.focus();
		await expect( popup ).toBeVisible();

		await trigger.blur();
		await expect( popup ).toBeHidden();
	} );

	test( 'Escape closes tooltip', async ( { page } ) => {
		const trigger = page
			.locator( '[data-bsui-tooltip-root]' )
			.first()
			.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).first();

		await trigger.focus();
		await expect( popup ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();
	} );

	test( 'instant tooltip shows on hover without delay', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await trigger.hover();
		await expect( popup ).toBeVisible( { timeout: 500 } );
	} );

	test( 'hover shows tooltip content', async ( { page } ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await trigger.hover();
		await expect( popup ).toContainText( 'No delay' );
	} );

	test( 'tooltip popup appears on mouseenter', async ( { page } ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await expect( popup ).toBeHidden();
		await trigger.hover();
		await expect( popup ).toBeVisible( { timeout: 500 } );
	} );

	test( 'tooltip popup disappears on mouse leave', async ( { page } ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await trigger.hover();
		await expect( popup ).toBeVisible( { timeout: 500 } );

		await page.mouse.move( 0, 0 );
		await expect( popup ).toBeHidden();
	} );

	test( 'focus on trigger shows tooltip', async ( { page } ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await expect( popup ).toBeHidden();
		await trigger.focus();
		await expect( popup ).toBeVisible();
	} );

	test( 'blur on trigger hides tooltip', async ( { page } ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await trigger.focus();
		await expect( popup ).toBeVisible();

		await trigger.blur();
		await expect( popup ).toBeHidden();
	} );

	test( 'Escape key closes tooltip when focused', async ( { page } ) => {
		const root = page.locator( '[data-bsui-tooltip-root]' ).nth( 1 );
		const trigger = root.locator( 'button' );
		const popup = page.locator( '[role="tooltip"]' ).nth( 1 );

		await trigger.focus();
		await expect( popup ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();
	} );
} );
