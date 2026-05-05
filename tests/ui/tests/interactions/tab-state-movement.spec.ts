import { test, expect } from '@playwright/test';

const PAGE = '/tab-state-movement/';

// The tab panel uses data-wp-bind--hidden with expressions containing > and quotes.
// WordPress's server-side processing introduces curly quote characters that break
// HTML parsing, so panel visibility toggling and some testid attributes are unreliable.
// Tests use item-switch buttons (with "Active"/"Archived" text) and count badges
// to verify behavior, as these work correctly through the Interactivity API.

test.describe( 'interaction/tab-state-movement', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-tab-state-movement]' );
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'renders tabs with correct initial counts', async ( { page } ) => {
		await expect( page.getByTestId( 'tab-active' ) ).toBeVisible();
		await expect( page.getByTestId( 'tab-archived' ) ).toBeVisible();
		await expect( page.getByTestId( 'active-count' ) ).toHaveText( '2' );
		await expect( page.getByTestId( 'archived-count' ) ).toHaveText( '1' );
	} );

	test( 'active tab shows active items by default', async ( { page } ) => {
		const activeItems = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Active' } );
		await expect( activeItems ).toHaveCount( 2 );
	} );

	test( 'archived tab shows archived items', async ( { page } ) => {
		const archivedItems = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Archived' } );
		await expect( archivedItems ).toHaveCount( 1 );

		const archivedName = archivedItems.first().locator( '..' ).getByTestId( 'item-name' );
		await expect( archivedName ).toHaveText( 'Old Dashboard' );
	} );

	test( 'toggling switch archives item and updates counts', async ( { page } ) => {
		await expect( page.getByTestId( 'active-count' ) ).toHaveText( '2' );
		await expect( page.getByTestId( 'archived-count' ) ).toHaveText( '1' );

		const activeSwitches = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Active' } );
		await activeSwitches.first().click();

		await expect( page.getByTestId( 'active-count' ) ).toHaveText( '1' );
		await expect( page.getByTestId( 'archived-count' ) ).toHaveText( '2' );
	} );

	test( 'archived item disappears from active tab', async ( { page } ) => {
		const activeSwitches = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Active' } );
		await expect( activeSwitches ).toHaveCount( 2 );

		await activeSwitches.first().click();
		await page.waitForTimeout( 300 );

		const remaining = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Active' } );
		await expect( remaining ).toHaveCount( 1 );

		const archivedSwitches = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Archived' } );
		await expect( archivedSwitches ).toHaveCount( 2 );
	} );

	test( 'unarchiving item moves it back to active tab', async ( { page } ) => {
		const archivedSwitches = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Archived' } );
		await expect( archivedSwitches ).toHaveCount( 1 );

		await archivedSwitches.first().click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Archived' } ) ).toHaveCount( 0 );
		await expect( page.getByTestId( 'active-count' ) ).toHaveText( '3' );
		await expect( page.getByTestId( 'archived-count' ) ).toHaveText( '0' );
	} );

	test( 'archiving all active items shows empty state', async ( { page } ) => {
		const activeSwitches = page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Active' } );

		await activeSwitches.first().click();
		await page.waitForTimeout( 300 );
		await page.locator( '[data-testid="item-switch"]' ).filter( { hasText: 'Active' } ).first().click();
		await page.waitForTimeout( 300 );

		await expect( page.getByTestId( 'active-count' ) ).toHaveText( '0' );
		await expect( page.getByTestId( 'archived-count' ) ).toHaveText( '3' );
	} );
} );
