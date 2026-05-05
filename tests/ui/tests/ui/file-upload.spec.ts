import { test, expect } from '@playwright/test';

const PAGE = '/file-upload-test/';

test.describe( 'bsui/file-upload', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-file-upload]' );
	} );

	test( 'renders upload area', async ( { page } ) => {
		const root = page.locator( '[data-bsui-file-upload]' );
		await expect( root ).toBeVisible();
	} );

	test( 'has hidden file input', async ( { page } ) => {
		const root = page.locator( '[data-bsui-file-upload]' );
		const fileInput = root.locator( 'input[type="file"]' );

		await expect( fileInput ).toHaveCount( 1 );
		await expect( fileInput ).toBeHidden();
	} );

	test( 'file input accepts image/*', async ( { page } ) => {
		const root = page.locator( '[data-bsui-file-upload]' );
		const fileInput = root.locator( 'input[type="file"]' );

		await expect( fileInput ).toHaveAttribute( 'accept', 'image/*' );
	} );
} );
