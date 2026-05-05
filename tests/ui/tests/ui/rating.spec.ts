import { test, expect } from '@playwright/test';

const PAGE = '/rating-test/';

test.describe( 'bsui/rating', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-rating]' );
	} );

	test( 'renders 5 star buttons', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );

		await expect( stars ).toHaveCount( 5 );
	} );

	test( 'default value shows 3 filled stars', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const filledStars = root.locator( 'button[data-star][data-filled]' );

		await expect( filledStars ).toHaveCount( 3 );
	} );

	test( 'clicking star 4 updates to 4 filled', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );

		await stars.nth( 3 ).click();
		await page.waitForTimeout( 200 );

		const filledStars = root.locator( 'button[data-star][data-filled]' );
		await expect( filledStars ).toHaveCount( 4 );
	} );

	test( 'clicking same star again resets to 0', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );

		// Default is 3, click star 3 (index 2) to reset
		await stars.nth( 2 ).click();
		await page.waitForTimeout( 200 );

		const filledStars = root.locator( 'button[data-star][data-filled]' );
		await expect( filledStars ).toHaveCount( 0 );
	} );

	test( 'has hidden input with value', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const hiddenInput = root.locator( 'input[type="hidden"]' );

		await expect( hiddenInput ).toHaveCount( 1 );
		await expect( hiddenInput ).toHaveAttribute( 'value', '3' );
	} );

	test( 'keyboard: focus star and press Enter selects it', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );

		await stars.nth( 4 ).focus();
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 200 );

		const filledStars = root.locator( 'button[data-star][data-filled]' );
		await expect( filledStars ).toHaveCount( 5 );
	} );

	test( 'keyboard: focus star and press Space selects it', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );

		await stars.nth( 0 ).focus();
		await page.keyboard.press( 'Space' );
		await page.waitForTimeout( 200 );

		const filledStars = root.locator( 'button[data-star][data-filled]' );
		await expect( filledStars ).toHaveCount( 1 );
	} );

	test( 'hidden input value updates to match selection', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );
		const hiddenInput = root.locator( 'input[type="hidden"]' );

		await expect( hiddenInput ).toHaveAttribute( 'value', '3' );

		await stars.nth( 4 ).click();
		await page.waitForTimeout( 200 );
		await expect( hiddenInput ).toHaveAttribute( 'value', '5' );

		await stars.nth( 1 ).click();
		await page.waitForTimeout( 200 );
		await expect( hiddenInput ).toHaveAttribute( 'value', '2' );
	} );

	test( 'hover shows preview with data-filled on hovered stars', async ( { page } ) => {
		const root = page.locator( '[data-bsui-rating]' );
		const stars = root.locator( 'button[data-star]' );

		await stars.nth( 1 ).hover();
		await page.waitForTimeout( 200 );

		const filledStars = root.locator( 'button[data-star][data-filled]' );
		await expect( filledStars ).toHaveCount( 2 );

		const thirdStar = stars.nth( 2 );
		await expect( thirdStar ).not.toHaveAttribute( 'data-filled', '' );
	} );
} );
