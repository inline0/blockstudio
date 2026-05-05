import { test, expect } from '@playwright/test';

const PAGE = '/select-test/';

async function openSelect( page ) {
	const root = page.locator( '[data-bsui-select-root]' );
	const trigger = root.locator( '[data-bsui-select-trigger]' );
	const listbox = root.locator( '[role="listbox"]' );

	await trigger.click();
	await expect( listbox ).toBeVisible();
	const firstOption = root.locator(
		'[role="option"]:not([aria-disabled="true"])'
	).first();
	await expect( firstOption ).toBeFocused();

	return { root, trigger, listbox };
}

test.describe( 'bsui/select', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-select-root]' );
	} );

	test( 'shows placeholder by default', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-select-trigger]' );
		await expect( trigger ).toContainText( 'Choose a fruit' );
	} );

	test( 'listbox is hidden by default', async ( { page } ) => {
		const root = page.locator( '[data-bsui-select-root]' );
		const listbox = root.locator( '[role="listbox"]' );
		await expect( listbox ).toBeHidden();
	} );

	test( 'trigger has aria-haspopup=listbox', async ( { page } ) => {
		const trigger = page.locator( '[data-bsui-select-trigger]' );
		await expect( trigger ).toHaveAttribute(
			'aria-haspopup',
			'listbox'
		);
	} );

	test( 'clicking trigger opens select', async ( { page } ) => {
		const root = page.locator( '[data-bsui-select-root]' );
		const trigger = root.locator( '[data-bsui-select-trigger]' );
		const listbox = root.locator( '[role="listbox"]' );

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await expect( trigger ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
	} );

	test( 'options have role=option', async ( { page } ) => {
		const { root } = await openSelect( page );
		const options = root.locator( '[role="option"]' );
		await expect( options ).toHaveCount( 4 );
	} );

	test( 'clicking option selects it and closes', async ( { page } ) => {
		const { root, trigger, listbox } = await openSelect( page );

		const option = root
			.locator( '[role="option"]' )
			.filter( { hasText: 'Banana' } );
		await option.click();

		await expect( listbox ).toBeHidden();
		await expect( trigger ).toContainText( 'Banana' );
	} );

	test( 'ArrowDown navigates options', async ( { page } ) => {
		const { root } = await openSelect( page );
		const options = root.locator(
			'[role="option"]:not([aria-disabled="true"])'
		);

		await page.keyboard.press( 'ArrowDown' );
		await expect( options.nth( 1 ) ).toBeFocused();
	} );

	test( 'Enter selects focused option', async ( { page } ) => {
		const { root, trigger, listbox } = await openSelect( page );

		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Enter' );

		await expect( listbox ).toBeHidden();
		await expect( trigger ).toContainText( 'Banana' );
	} );

	test( 'Escape closes without selecting', async ( { page } ) => {
		const { trigger, listbox } = await openSelect( page );

		await page.keyboard.press( 'Escape' );
		await expect( listbox ).toBeHidden();
		await expect( trigger ).toContainText( 'Choose a fruit' );
	} );

	test( 'hidden form input updates on selection', async ( {
		page,
	} ) => {
		const root = page.locator( '[data-bsui-select-root]' );
		const trigger = root.locator( '[data-bsui-select-trigger]' );
		const input = root.locator( 'input[name="fruit"]' );

		await trigger.click();
		const listbox = root.locator( '[role="listbox"]' );
		await expect( listbox ).toBeVisible();

		const option = root
			.locator( '[role="option"]' )
			.filter( { hasText: 'Cherry' } );
		await option.click();

		await expect( input ).toHaveAttribute( 'value', 'cherry' );
	} );

	test( 'clicking outside closes select', async ( { page } ) => {
		const { listbox } = await openSelect( page );

		await page.click( 'body', { position: { x: 10, y: 10 } } );
		await expect( listbox ).toBeHidden();
	} );

	test( 'ArrowDown on trigger opens select', async ( { page } ) => {
		const root = page.locator( '[data-bsui-select-root]' );
		const trigger = root.locator( '[data-bsui-select-trigger]' );
		const listbox = root.locator( '[role="listbox"]' );

		await trigger.focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( listbox ).toBeVisible();
	} );

	test( 'selected option has aria-selected=true', async ( {
		page,
	} ) => {
		const { root, trigger } = await openSelect( page );

		const apple = root
			.locator( '[role="option"]' )
			.filter( { hasText: 'Apple' } );
		await apple.click();

		await trigger.click();
		const listbox = root.locator( '[role="listbox"]' );
		await expect( listbox ).toBeVisible();

		const selectedApple = root
			.locator( '[role="option"]' )
			.filter( { hasText: 'Apple' } );
		await expect( selectedApple ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'ArrowUp navigates options', async ( { page } ) => {
		const { root } = await openSelect( page );
		const options = root.locator(
			'[role="option"]:not([aria-disabled="true"])'
		);

		await page.keyboard.press( 'ArrowDown' );
		await expect( options.nth( 1 ) ).toBeFocused();

		await page.keyboard.press( 'ArrowUp' );
		await expect( options.nth( 0 ) ).toBeFocused();
	} );

	test( 'selected option updates trigger text', async ( {
		page,
	} ) => {
		const { root, trigger } = await openSelect( page );

		await expect( trigger ).toContainText( 'Choose a fruit' );

		const cherry = root
			.locator( '[role="option"]' )
			.filter( { hasText: 'Cherry' } );
		await cherry.click();

		await expect( trigger ).toContainText( 'Cherry' );
	} );
} );
