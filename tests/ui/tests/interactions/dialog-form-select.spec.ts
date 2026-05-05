import { test, expect } from '@playwright/test';

const PAGE = '/dialog-form-select/';

test.describe( 'interaction/dialog-form-select', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-dialog-form-select]' );
	} );

	test( 'renders add item button and initial items', async ( { page } ) => {
		await expect( page.getByTestId( 'add-item-button' ) ).toBeVisible();
		const items = page.getByTestId( 'item-row' );
		await expect( items ).toHaveCount( 2 );
	} );

	test( 'dialog is hidden initially', async ( { page } ) => {
		await expect( page.getByTestId( 'dialog' ) ).toBeHidden();
	} );

	test( 'clicking add item opens dialog', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await expect( page.getByTestId( 'dialog' ) ).toBeVisible();
	} );

	test( 'select dropdown shows options when triggered', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await expect( page.getByTestId( 'category-popup' ) ).toBeHidden();

		await page.getByTestId( 'category-trigger' ).click();
		await expect( page.getByTestId( 'category-popup' ) ).toBeVisible();
		await expect( page.getByTestId( 'option-design' ) ).toBeVisible();
		await expect( page.getByTestId( 'option-dev' ) ).toBeVisible();
		await expect( page.getByTestId( 'option-marketing' ) ).toBeVisible();
	} );

	test( 'selecting a category updates the trigger text', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await page.getByTestId( 'category-trigger' ).click();
		await page.getByTestId( 'option-marketing' ).click();

		await expect( page.getByTestId( 'select-value' ) ).toHaveText( 'Marketing' );
		await expect( page.getByTestId( 'category-popup' ) ).toBeHidden();
	} );

	test( 'full flow: open dialog, fill form, submit, verify item in list', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await page.getByTestId( 'item-name-input' ).fill( 'New Website' );
		await page.getByTestId( 'category-trigger' ).click();
		await page.getByTestId( 'option-design' ).click();
		await page.getByTestId( 'dialog-submit' ).click();

		await expect( page.getByTestId( 'dialog' ) ).toBeHidden();

		const items = page.getByTestId( 'item-row' );
		await expect( items ).toHaveCount( 3 );

		const lastItem = items.last();
		await expect( lastItem.getByTestId( 'item-name' ) ).toHaveText( 'New Website' );
		await expect( lastItem.getByTestId( 'item-category-badge' ) ).toHaveText( 'Design' );
	} );

	test( 'dialog closes and form resets after submission', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await page.getByTestId( 'item-name-input' ).fill( 'Test Item' );
		await page.getByTestId( 'category-trigger' ).click();
		await page.getByTestId( 'option-dev' ).click();
		await page.getByTestId( 'dialog-submit' ).click();

		await expect( page.getByTestId( 'dialog' ) ).toBeHidden();

		await page.getByTestId( 'add-item-button' ).click();
		await expect( page.getByTestId( 'item-name-input' ) ).toHaveValue( '' );
		await expect( page.getByTestId( 'select-placeholder' ) ).toBeVisible();
	} );

	test( 'adding multiple items accumulates in the list', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await page.getByTestId( 'item-name-input' ).fill( 'Item A' );
		await page.getByTestId( 'category-trigger' ).click();
		await page.getByTestId( 'option-design' ).click();
		await page.getByTestId( 'dialog-submit' ).click();

		await page.getByTestId( 'add-item-button' ).click();
		await page.getByTestId( 'item-name-input' ).fill( 'Item B' );
		await page.getByTestId( 'category-trigger' ).click();
		await page.getByTestId( 'option-marketing' ).click();
		await page.getByTestId( 'dialog-submit' ).click();

		const items = page.getByTestId( 'item-row' );
		await expect( items ).toHaveCount( 4 );
	} );

	test( 'empty form does not submit', async ( { page } ) => {
		await page.getByTestId( 'add-item-button' ).click();
		await page.getByTestId( 'dialog-submit' ).click();

		await expect( page.getByTestId( 'dialog' ) ).toBeVisible();
		const items = page.getByTestId( 'item-row' );
		await expect( items ).toHaveCount( 2 );
	} );
} );
