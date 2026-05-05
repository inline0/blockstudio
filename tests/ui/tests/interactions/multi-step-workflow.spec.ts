import { test, expect } from '@playwright/test';

const PAGE = '/multi-step-workflow/';

test.describe( 'interaction/multi-step-workflow', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-interaction-multi-step-workflow]' );
		await page.waitForLoadState( 'networkidle' );
	} );

	test( 'starts on step 1', async ( { page } ) => {
		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 1 of 3' );
		await expect( page.locator( '[data-testid="step-1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-testid="step-2"]' ) ).toBeHidden();
		await expect( page.locator( '[data-testid="step-3"]' ) ).toBeHidden();
	} );

	test( 'shows error when continuing with empty email', async ( { page } ) => {
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="error-message"]' ) ).toBeVisible();
		await expect( page.locator( '[data-testid="error-message"]' ) ).toHaveText( 'Email is required.' );
		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 1 of 3' );
	} );

	test( 'proceeds to step 2 after entering email', async ( { page } ) => {
		await page.locator( '[data-testid="email-input"]' ).fill( 'test@example.com' );
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 2 of 3' );
		await expect( page.locator( '[data-testid="step-1"]' ) ).toBeHidden();
		await expect( page.locator( '[data-testid="step-2"]' ) ).toBeVisible();
	} );

	test( 'shows error when continuing step 2 with empty name', async ( { page } ) => {
		await page.locator( '[data-testid="email-input"]' ).fill( 'test@example.com' );
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );

		await page.locator( '[data-testid="continue-btn-2"]' ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="error-message-2"]' ) ).toBeVisible();
		await expect( page.locator( '[data-testid="error-message-2"]' ) ).toHaveText( 'Name is required.' );
	} );

	test( 'back button returns to step 1 from step 2', async ( { page } ) => {
		await page.locator( '[data-testid="email-input"]' ).fill( 'test@example.com' );
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );

		await page.locator( '[data-testid="back-btn"]' ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 1 of 3' );
		await expect( page.locator( '[data-testid="step-1"]' ) ).toBeVisible();
	} );

	test( 'proceeds to step 3 and shows summary', async ( { page } ) => {
		await page.locator( '[data-testid="email-input"]' ).fill( 'test@example.com' );
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );

		await page.locator( '[data-testid="name-input"]' ).fill( 'John Doe' );
		await page.locator( '[data-testid="continue-btn-2"]' ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 3 of 3' );
		await expect( page.locator( '[data-testid="step-3"]' ) ).toBeVisible();
		await expect( page.locator( '[data-testid="summary-email"]' ) ).toHaveText( 'test@example.com' );
		await expect( page.locator( '[data-testid="summary-name"]' ) ).toHaveText( 'John Doe' );
	} );

	test( 'submit shows success message', async ( { page } ) => {
		await page.locator( '[data-testid="email-input"]' ).fill( 'test@example.com' );
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );

		await page.locator( '[data-testid="name-input"]' ).fill( 'John Doe' );
		await page.locator( '[data-testid="continue-btn-2"]' ).click();
		await page.waitForTimeout( 300 );

		await page.locator( '[data-testid="submit-btn"]' ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="success-message"]' ) ).toBeVisible();
		await expect( page.locator( '[data-testid="success-message"]' ) ).toContainText( 'Successfully submitted' );
	} );

	test( 'full workflow from start to finish', async ( { page } ) => {
		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 1 of 3' );

		await page.locator( '[data-testid="email-input"]' ).fill( 'jane@test.org' );
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 2 of 3' );
		await page.locator( '[data-testid="name-input"]' ).fill( 'Jane Smith' );
		await page.locator( '[data-testid="continue-btn-2"]' ).click();
		await page.waitForTimeout( 300 );

		await expect( page.locator( '[data-testid="step-indicator"]' ) ).toHaveText( 'Step 3 of 3' );
		await expect( page.locator( '[data-testid="summary-email"]' ) ).toHaveText( 'jane@test.org' );
		await expect( page.locator( '[data-testid="summary-name"]' ) ).toHaveText( 'Jane Smith' );

		await page.locator( '[data-testid="submit-btn"]' ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="success-message"]' ) ).toBeVisible();
	} );

	test( 'error clears when user starts typing', async ( { page } ) => {
		await page.locator( '[data-testid="continue-btn"]' ).click();
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="error-message"]' ) ).toBeVisible();

		await page.locator( '[data-testid="email-input"]' ).fill( 'a' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '[data-testid="error-message"]' ) ).toBeHidden();
	} );
} );
