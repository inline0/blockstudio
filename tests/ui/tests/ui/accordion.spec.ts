import { test, expect } from '@playwright/test';

const PAGE = '/accordion-test/';

test.describe( 'bsui/accordion', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-accordion-trigger]' );
	} );

	test( 'renders all triggers', async ( { page } ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await expect( triggers ).toHaveCount( 3 );
	} );

	test( 'renders all panels', async ( { page } ) => {
		const panels = page.locator( '[role="region"]' );
		expect( await panels.count() ).toBe( 3 );
	} );

	test( 'renders default open item expanded', async ( { page } ) => {
		const trigger1 = page.locator( '#ui-accordion-trigger-item1' );
		await expect( trigger1 ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
		const panel1 = page.locator( '#ui-accordion-panel-item1' );
		await expect( panel1 ).toBeVisible();
	} );

	test( 'renders other items collapsed', async ( { page } ) => {
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const trigger3 = page.locator( '#ui-accordion-trigger-item3' );
		await expect( trigger2 ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
		await expect( trigger3 ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
	} );

	test( 'trigger has aria-controls pointing to panel', async ( {
		page,
	} ) => {
		const trigger = page.locator( '#ui-accordion-trigger-item1' );
		await expect( trigger ).toHaveAttribute(
			'aria-controls',
			'ui-accordion-panel-item1'
		);
	} );

	test( 'panel has aria-labelledby pointing to trigger', async ( {
		page,
	} ) => {
		const panel = page.locator( '#ui-accordion-panel-item1' );
		await expect( panel ).toHaveAttribute(
			'aria-labelledby',
			'ui-accordion-trigger-item1'
		);
	} );

	test( 'panel has role=region', async ( { page } ) => {
		const panel = page.locator( '#ui-accordion-panel-item1' );
		await expect( panel ).toHaveAttribute( 'role', 'region' );
	} );

	test( 'clicking closed trigger opens it', async ( { page } ) => {
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const panel2 = page.locator( '#ui-accordion-panel-item2' );

		await trigger2.click();
		await expect( trigger2 ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
		await expect( panel2 ).toBeVisible();
	} );

	test( 'clicking opens new item and closes previous (single mode)', async ( {
		page,
	} ) => {
		const trigger1 = page.locator( '#ui-accordion-trigger-item1' );
		const panel1 = page.locator( '#ui-accordion-panel-item1' );
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const panel2 = page.locator( '#ui-accordion-panel-item2' );

		await trigger2.click();

		await expect( trigger2 ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
		await expect( panel2 ).toBeVisible();
		await expect( trigger1 ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
		await expect( panel1 ).toBeHidden();
	} );

	test( 'clicking open trigger closes it (collapsible)', async ( {
		page,
	} ) => {
		const trigger1 = page.locator( '#ui-accordion-trigger-item1' );
		const panel1 = page.locator( '#ui-accordion-panel-item1' );
		await trigger1.click();
		await expect( trigger1 ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
		await expect( panel1 ).toBeHidden();
	} );

	test( 'ArrowDown moves focus to next trigger', async ( { page } ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await triggers.first().focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( triggers.nth( 1 ) ).toBeFocused();
	} );

	test( 'ArrowUp moves focus to previous trigger', async ( {
		page,
	} ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await triggers.nth( 1 ).focus();
		await page.keyboard.press( 'ArrowUp' );
		await expect( triggers.first() ).toBeFocused();
	} );

	test( 'ArrowDown wraps from last to first', async ( { page } ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await triggers.nth( 2 ).focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( triggers.first() ).toBeFocused();
	} );

	test( 'ArrowUp wraps from first to last', async ( { page } ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await triggers.first().focus();
		await page.keyboard.press( 'ArrowUp' );
		await expect( triggers.nth( 2 ) ).toBeFocused();
	} );

	test( 'Home moves focus to first trigger', async ( { page } ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await triggers.nth( 2 ).focus();
		await page.keyboard.press( 'Home' );
		await expect( triggers.first() ).toBeFocused();
	} );

	test( 'End moves focus to last trigger', async ( { page } ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		await triggers.first().focus();
		await page.keyboard.press( 'End' );
		await expect( triggers.nth( 2 ) ).toBeFocused();
	} );

	test( 'Enter toggles focused trigger', async ( { page } ) => {
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const panel2 = page.locator( '#ui-accordion-panel-item2' );

		await trigger2.focus();
		await page.keyboard.press( 'Enter' );
		await expect( panel2 ).toBeVisible();
	} );

	test( 'Space toggles focused trigger', async ( { page } ) => {
		const trigger3 = page.locator( '#ui-accordion-trigger-item3' );
		const panel3 = page.locator( '#ui-accordion-panel-item3' );

		await trigger3.focus();
		await page.keyboard.press( 'Space' );
		await expect( panel3 ).toBeVisible();
	} );

	test( 'keyboard does not activate on focus (manual mode)', async ( {
		page,
	} ) => {
		const triggers = page.locator( '[data-bsui-accordion-trigger]' );
		const panel1 = page.locator( '#ui-accordion-panel-item1' );

		await triggers.first().focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( triggers.nth( 1 ) ).toBeFocused();
		await expect( panel1 ).toBeVisible();
	} );

	test( 'panel content is correct', async ( { page } ) => {
		const panel1 = page.locator( '#ui-accordion-panel-item1' );
		await expect( panel1 ).toContainText( 'First panel content.' );
	} );

	// Animation

	test( 'initially open panel has bs-ui-open class and height variable', async ( { page } ) => {
		const panel1 = page.locator( '#ui-accordion-panel-item1' );
		await expect( panel1 ).toHaveClass( /bs-ui-open/ );
		const height = await panel1.evaluate(
			( el ) => getComputedStyle( el ).getPropertyValue( '--bs-ui-panel-height' )
		);
		expect( height ).toMatch( /^\d+px$/ );
	} );

	test( 'closed panels do not have bs-ui-open class', async ( { page } ) => {
		const panel2 = page.locator( '#ui-accordion-panel-item2' );
		const panel3 = page.locator( '#ui-accordion-panel-item3' );
		await expect( panel2 ).not.toHaveClass( /bs-ui-open/ );
		await expect( panel3 ).not.toHaveClass( /bs-ui-open/ );
	} );

	test( 'opening a never-opened panel adds bs-ui-open and height', async ( { page } ) => {
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const panel2 = page.locator( '#ui-accordion-panel-item2' );

		await trigger2.click();
		await expect( panel2 ).toHaveClass( /bs-ui-open/ );
		await expect( panel2 ).toBeVisible();
		const height = await panel2.evaluate(
			( el ) => getComputedStyle( el ).getPropertyValue( '--bs-ui-panel-height' )
		);
		expect( height ).toMatch( /^\d+px$/ );
		expect( parseInt( height ) ).toBeGreaterThan( 0 );
	} );

	test( 'opening a second never-opened panel works', async ( { page } ) => {
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const trigger3 = page.locator( '#ui-accordion-trigger-item3' );
		const panel3 = page.locator( '#ui-accordion-panel-item3' );

		await trigger2.click();
		await expect( page.locator( '#ui-accordion-panel-item2' ) ).toBeVisible();
		await trigger3.click();
		await expect( panel3 ).toHaveClass( /bs-ui-open/ );
		await expect( panel3 ).toBeVisible();
		const height = await panel3.evaluate(
			( el ) => getComputedStyle( el ).getPropertyValue( '--bs-ui-panel-height' )
		);
		expect( parseInt( height ) ).toBeGreaterThan( 0 );
	} );

	test( 'closing removes bs-ui-open class after transition', async ( { page } ) => {
		const trigger1 = page.locator( '#ui-accordion-trigger-item1' );
		const panel1 = page.locator( '#ui-accordion-panel-item1' );

		await trigger1.click();
		await expect( panel1 ).not.toHaveClass( /bs-ui-open/ );
		await expect( panel1 ).toBeHidden();
	} );

	test( 'close then reopen the same panel', async ( { page } ) => {
		const trigger1 = page.locator( '#ui-accordion-trigger-item1' );
		const panel1 = page.locator( '#ui-accordion-panel-item1' );

		await trigger1.click();
		await expect( panel1 ).toBeHidden();
		await trigger1.click();
		await expect( panel1 ).toHaveClass( /bs-ui-open/ );
		await expect( panel1 ).toBeVisible();
	} );

	test( 'rapid toggling works correctly', async ( { page } ) => {
		const trigger1 = page.locator( '#ui-accordion-trigger-item1' );
		const trigger2 = page.locator( '#ui-accordion-trigger-item2' );
		const trigger3 = page.locator( '#ui-accordion-trigger-item3' );

		await trigger2.click();
		await trigger3.click();
		await trigger1.click();

		await expect( trigger1 ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
		const panel1 = page.locator( '#ui-accordion-panel-item1' );
		await expect( panel1 ).toBeVisible();
		const visiblePanels = page.locator(
			'[role="region"].bs-ui-open'
		);
		await expect( visiblePanels ).toHaveCount( 1 );
	} );
} );
