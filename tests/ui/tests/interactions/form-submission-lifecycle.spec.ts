import { test, expect } from '@playwright/test';

const PAGE = '/form-submission-lifecycle/';

test.describe( 'interaction/form-submission-lifecycle', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-form-submission-lifecycle]' );
	} );

	test( 'renders form with inputs and submit button', async ( { page } ) => {
		await expect( page.getByTestId( 'name-input' ) ).toBeVisible();
		await expect( page.getByTestId( 'email-input' ) ).toBeVisible();
		await expect( page.getByTestId( 'submit-button' ) ).toBeVisible();
	} );

	test( 'status message is hidden initially', async ( { page } ) => {
		await expect( page.getByTestId( 'status-message' ) ).toBeHidden();
	} );

	test( 'form gets bs-ui-submitting class during submit', async ( { page } ) => {
		const form = page.getByTestId( 'form' );

		await page.getByTestId( 'name-input' ).fill( 'John' );
		await page.getByTestId( 'email-input' ).fill( 'john@test.com' );
		await page.getByTestId( 'submit-button' ).click();

		await expect( form ).toHaveClass( /bs-ui-submitting/ );
	} );

	test( 'submit button is disabled during submit', async ( { page } ) => {
		await page.getByTestId( 'name-input' ).fill( 'John' );
		await page.getByTestId( 'email-input' ).fill( 'john@test.com' );
		await page.getByTestId( 'submit-button' ).click();

		const button = page.getByTestId( 'submit-button' );
		await expect( button ).toBeDisabled();
	} );

	test( 'form exits submitting state after completion', async ( { page } ) => {
		const form = page.getByTestId( 'form' );

		await page.getByTestId( 'name-input' ).fill( 'John' );
		await page.getByTestId( 'email-input' ).fill( 'john@test.com' );
		await page.getByTestId( 'submit-button' ).click();

		await expect( form ).toHaveClass( /bs-ui-submitting/ );
		await expect( form ).not.toHaveClass( /bs-ui-submitting/, { timeout: 5000 } );
	} );

	test( 'inputs clear after successful submission', async ( { page } ) => {
		await page.getByTestId( 'name-input' ).fill( 'John' );
		await page.getByTestId( 'email-input' ).fill( 'john@test.com' );
		await page.getByTestId( 'submit-button' ).click();

		await expect( page.getByTestId( 'status-message' ) ).toBeVisible( { timeout: 5000 } );
		await expect( page.getByTestId( 'name-input' ) ).toHaveValue( '' );
		await expect( page.getByTestId( 'email-input' ) ).toHaveValue( '' );
	} );

	test( 'status message appears after successful submission', async ( { page } ) => {
		await page.getByTestId( 'name-input' ).fill( 'John' );
		await page.getByTestId( 'email-input' ).fill( 'john@test.com' );
		await page.getByTestId( 'submit-button' ).click();

		await expect( page.getByTestId( 'status-message' ) ).toBeVisible( { timeout: 5000 } );
		await expect( page.getByTestId( 'status-message' ) ).toHaveText( 'Form submitted successfully' );
	} );

	test( 'button becomes disabled during submission then re-enables', async ( { page } ) => {
		await page.getByTestId( 'name-input' ).fill( 'John' );
		await page.getByTestId( 'email-input' ).fill( 'john@test.com' );

		const button = page.getByTestId( 'submit-button' );
		await expect( button ).toBeEnabled();

		await button.click();
		await expect( button ).toBeDisabled();

		await expect( button ).toBeEnabled( { timeout: 5000 } );
	} );
} );
