import { test, expect } from '@playwright/test';

const PAGE = '/checkbox-counter-sync/';

test.describe( 'interaction/checkbox-counter-sync', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-checkbox-counter-sync]' );
	} );

	test( 'renders all task items', async ( { page } ) => {
		const items = page.getByTestId( 'task-item' );
		await expect( items ).toHaveCount( 3 );
	} );

	test( 'counter shows 0/3 initially', async ( { page } ) => {
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '0/3 completed' );
	} );

	test( 'all checkboxes are unchecked initially', async ( { page } ) => {
		const checkboxes = page.getByTestId( 'task-checkbox' );
		for ( let i = 0; i < 3; i++ ) {
			await expect( checkboxes.nth( i ) ).not.toBeChecked();
		}
	} );

	test( 'checking one item updates counter to 1/3', async ( { page } ) => {
		await page.getByTestId( 'task-checkbox' ).first().check();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '1/3 completed' );
	} );

	test( 'checking two items updates counter to 2/3', async ( { page } ) => {
		await page.getByTestId( 'task-checkbox' ).first().check();
		await page.getByTestId( 'task-checkbox' ).nth( 1 ).check();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '2/3 completed' );
	} );

	test( 'unchecking an item decreases the counter', async ( { page } ) => {
		await page.getByTestId( 'task-checkbox' ).first().check();
		await page.getByTestId( 'task-checkbox' ).nth( 1 ).check();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '2/3 completed' );

		await page.getByTestId( 'task-checkbox' ).first().uncheck();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '1/3 completed' );
	} );

	test( 'Complete All checks all items and shows 3/3', async ( { page } ) => {
		await page.getByTestId( 'complete-all-button' ).click();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '3/3 completed' );

		const checkboxes = page.getByTestId( 'task-checkbox' );
		for ( let i = 0; i < 3; i++ ) {
			await expect( checkboxes.nth( i ) ).toBeChecked();
		}
	} );

	test( 'Reset All unchecks all items and shows 0/3', async ( { page } ) => {
		await page.getByTestId( 'complete-all-button' ).click();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '3/3 completed' );

		await page.getByTestId( 'reset-all-button' ).click();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '0/3 completed' );

		const checkboxes = page.getByTestId( 'task-checkbox' );
		for ( let i = 0; i < 3; i++ ) {
			await expect( checkboxes.nth( i ) ).not.toBeChecked();
		}
	} );

	test( 'full sequence: check, check, uncheck, complete all, reset all', async ( { page } ) => {
		await page.getByTestId( 'task-checkbox' ).first().check();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '1/3 completed' );

		await page.getByTestId( 'task-checkbox' ).nth( 1 ).check();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '2/3 completed' );

		await page.getByTestId( 'task-checkbox' ).first().uncheck();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '1/3 completed' );

		await page.getByTestId( 'complete-all-button' ).click();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '3/3 completed' );

		await page.getByTestId( 'reset-all-button' ).click();
		await expect( page.getByTestId( 'counter-badge' ) ).toHaveText( '0/3 completed' );
	} );
} );
