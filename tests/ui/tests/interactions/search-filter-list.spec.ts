import { test, expect } from '@playwright/test';

const PAGE = '/search-filter-list/';

test.describe( 'interaction/search-filter-list', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-search-filter-list]' );
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'all 7 items visible initially', async ( { page } ) => {
		const items = page.locator( '[data-testid="list-item"]' );
		await expect( items ).toHaveCount( 7 );
	} );

	test( 'initial count badge shows 7', async ( { page } ) => {
		await expect( page.locator( '[data-testid="item-count"]' ) ).toHaveText( '7' );
	} );

	test( 'all fruit names are rendered', async ( { page } ) => {
		const list = page.locator( '[data-testid="item-list"]' );
		await expect( list ).toContainText( 'Apple' );
		await expect( list ).toContainText( 'Banana' );
		await expect( list ).toContainText( 'Cherry' );
		await expect( list ).toContainText( 'Date' );
		await expect( list ).toContainText( 'Elderberry' );
		await expect( list ).toContainText( 'Fig' );
		await expect( list ).toContainText( 'Grape' );
	} );

	test( 'empty state is hidden initially', async ( { page } ) => {
		await expect( page.locator( '[data-testid="empty-state"]' ) ).toBeHidden();
	} );

	test( 'typing "an" filters to Banana containing "an"', async ( { page } ) => {
		await page.locator( '[data-testid="search-input"]' ).fill( 'an' );
		await page.waitForTimeout( 300 );
		const items = page.locator( '[data-testid="list-item"]' );
		await expect( items ).toHaveCount( 1 );
		await expect( items.first() ).toHaveText( 'Banana' );
	} );

	test( 'count badge updates when filtering', async ( { page } ) => {
		await page.locator( '[data-testid="search-input"]' ).fill( 'an' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="item-count"]' ) ).toHaveText( '1' );
	} );

	test( 'clearing search restores all 7 items', async ( { page } ) => {
		const input = page.locator( '[data-testid="search-input"]' );
		await input.fill( 'an' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="list-item"]' ) ).toHaveCount( 1 );

		await input.fill( '' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="list-item"]' ) ).toHaveCount( 7 );
		await expect( page.locator( '[data-testid="item-count"]' ) ).toHaveText( '7' );
	} );

	test( 'typing "zzz" shows 0 items and empty state', async ( { page } ) => {
		await page.locator( '[data-testid="search-input"]' ).fill( 'zzz' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="list-item"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '[data-testid="item-count"]' ) ).toHaveText( '0' );
		await expect( page.locator( '[data-testid="empty-state"]' ) ).toBeVisible();
	} );

	test( 'search is case-insensitive', async ( { page } ) => {
		await page.locator( '[data-testid="search-input"]' ).fill( 'APPLE' );
		await page.waitForTimeout( 300 );
		const items = page.locator( '[data-testid="list-item"]' );
		await expect( items ).toHaveCount( 1 );
		await expect( items.first() ).toHaveText( 'Apple' );
	} );

	test( 'filtering by single letter "e" matches multiple items', async ( { page } ) => {
		await page.locator( '[data-testid="search-input"]' ).fill( 'e' );
		await page.waitForTimeout( 300 );
		const items = page.locator( '[data-testid="list-item"]' );
		const count = await items.count();
		expect( count ).toBeGreaterThan( 1 );
		const texts = await items.allTextContents();
		for ( const text of texts ) {
			expect( text.toLowerCase() ).toContain( 'e' );
		}
	} );
} );
