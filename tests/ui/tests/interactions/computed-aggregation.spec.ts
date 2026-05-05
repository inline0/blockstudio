import { test, expect } from '@playwright/test';

const PAGE = '/computed-aggregation/';

test.describe( 'interaction/computed-aggregation', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-computed-aggregation]' );
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'renders 3 product rows', async ( { page } ) => {
		const rows = page.locator( '[data-testid="product-row"]' );
		await expect( rows ).toHaveCount( 3 );
	} );

	test( 'initial total items is 6', async ( { page } ) => {
		// 2 + 1 + 3 = 6
		await expect( page.locator( '[data-testid="badge-total-items"]' ) ).toContainText( '6' );
	} );

	test( 'initial total value is 130', async ( { page } ) => {
		// (2*25) + (1*50) + (3*10) = 50 + 50 + 30 = 130
		await expect( page.locator( '[data-testid="badge-total-value"]' ) ).toContainText( '130' );
	} );

	test( 'initial average price is correct', async ( { page } ) => {
		// 130 / 6 = 21.67
		await expect( page.locator( '[data-testid="badge-average-price"]' ) ).toContainText( '21.67' );
	} );

	test( 'initial quantities are correct', async ( { page } ) => {
		const qtyValues = page.locator( '[data-testid="qty-value"]' );
		await expect( qtyValues.nth( 0 ) ).toHaveText( '2' );
		await expect( qtyValues.nth( 1 ) ).toHaveText( '1' );
		await expect( qtyValues.nth( 2 ) ).toHaveText( '3' );
	} );

	test( 'incrementing quantity updates total items', async ( { page } ) => {
		// Increment Widget Alpha (row 0) from 2 to 3
		await page.locator( '[data-testid="qty-increment"]' ).nth( 0 ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="qty-value"]' ).nth( 0 ) ).toHaveText( '3' );
		// 3 + 1 + 3 = 7
		await expect( page.locator( '[data-testid="badge-total-items"]' ) ).toContainText( '7' );
	} );

	test( 'incrementing quantity updates total value', async ( { page } ) => {
		// Increment Widget Alpha (price 25) from 2 to 3
		await page.locator( '[data-testid="qty-increment"]' ).nth( 0 ).click();
		await page.waitForTimeout( 300 );

		// (3*25) + (1*50) + (3*10) = 75 + 50 + 30 = 155
		await expect( page.locator( '[data-testid="badge-total-value"]' ) ).toContainText( '155' );
	} );

	test( 'incrementing quantity updates average price', async ( { page } ) => {
		// Increment Widget Alpha (price 25) from 2 to 3
		await page.locator( '[data-testid="qty-increment"]' ).nth( 0 ).click();
		await page.waitForTimeout( 300 );

		// 155 / 7 = 22.14
		await expect( page.locator( '[data-testid="badge-average-price"]' ) ).toContainText( '22.14' );
	} );

	test( 'decrementing quantity updates all badges', async ( { page } ) => {
		// Decrement Widget Gamma (row 2) from 3 to 2
		await page.locator( '[data-testid="qty-decrement"]' ).nth( 2 ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="qty-value"]' ).nth( 2 ) ).toHaveText( '2' );
		// 2 + 1 + 2 = 5
		await expect( page.locator( '[data-testid="badge-total-items"]' ) ).toContainText( '5' );
		// (2*25) + (1*50) + (2*10) = 50 + 50 + 20 = 120
		await expect( page.locator( '[data-testid="badge-total-value"]' ) ).toContainText( '120' );
		// 120 / 5 = 24
		await expect( page.locator( '[data-testid="badge-average-price"]' ) ).toContainText( '24' );
	} );

	test( 'quantity does not go below 0', async ( { page } ) => {
		// Decrement Widget Beta (row 1, qty 1) twice
		await page.locator( '[data-testid="qty-decrement"]' ).nth( 1 ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="qty-value"]' ).nth( 1 ) ).toHaveText( '0' );

		await page.locator( '[data-testid="qty-decrement"]' ).nth( 1 ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="qty-value"]' ).nth( 1 ) ).toHaveText( '0' );
	} );

	test( 'row subtotals update when quantity changes', async ( { page } ) => {
		// Initial subtotal for Widget Alpha: 2 * 25 = 50
		await expect( page.locator( '[data-testid="row-subtotal"]' ).nth( 0 ) ).toHaveText( '50' );

		// Increment to 3 * 25 = 75
		await page.locator( '[data-testid="qty-increment"]' ).nth( 0 ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="row-subtotal"]' ).nth( 0 ) ).toHaveText( '75' );
	} );

	test( 'multiple increments accumulate correctly', async ( { page } ) => {
		// Increment Widget Beta 3 times: 1 -> 4
		await page.locator( '[data-testid="qty-increment"]' ).nth( 1 ).click();
		await page.locator( '[data-testid="qty-increment"]' ).nth( 1 ).click();
		await page.locator( '[data-testid="qty-increment"]' ).nth( 1 ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="qty-value"]' ).nth( 1 ) ).toHaveText( '4' );
		// (2*25) + (4*50) + (3*10) = 50 + 200 + 30 = 280
		await expect( page.locator( '[data-testid="badge-total-value"]' ) ).toContainText( '280' );
		// 2 + 4 + 3 = 9
		await expect( page.locator( '[data-testid="badge-total-items"]' ) ).toContainText( '9' );
	} );
} );
