import { test, expect } from '@playwright/test';

const PAGE = '/tabs-test/';

test.describe( 'bsui/tabs', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[role="tablist"]' );
	} );

	test( 'renders tablist with correct role', async ( { page } ) => {
		const tablist = page.locator( '[role="tablist"]' );
		await expect( tablist ).toBeVisible();
	} );

	test( 'renders all tab triggers', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await expect( tabs ).toHaveCount( 3 );
	} );

	test( 'renders all tab panels', async ( { page } ) => {
		const panels = page.locator( '[role="tabpanel"]' );
		expect( await panels.count() ).toBe( 3 );
	} );

	test( 'renders default tab as active', async ( { page } ) => {
		const activeTab = page.locator( '[role="tab"][aria-selected="true"]' );
		await expect( activeTab ).toHaveCount( 1 );
		await expect( activeTab ).toContainText( 'Features' );
	} );

	test( 'renders default panel as visible', async ( { page } ) => {
		const visiblePanel = page.locator(
			'[role="tabpanel"]:not([hidden])'
		);
		await expect( visiblePanel ).toHaveCount( 1 );
		await expect( visiblePanel ).toContainText(
			'Features content here.'
		);
	} );

	test( 'hides non-active panels', async ( { page } ) => {
		const hiddenPanels = page.locator( '[role="tabpanel"][hidden]' );
		await expect( hiddenPanels ).toHaveCount( 2 );
	} );

	test( 'active tab has aria-selected=true', async ( { page } ) => {
		const tab1 = page.locator( '[role="tab"]' ).first();
		await expect( tab1 ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'inactive tabs have aria-selected=false', async ( { page } ) => {
		const tab2 = page.locator( '[role="tab"]' ).nth( 1 );
		const tab3 = page.locator( '[role="tab"]' ).nth( 2 );
		await expect( tab2 ).toHaveAttribute( 'aria-selected', 'false' );
		await expect( tab3 ).toHaveAttribute( 'aria-selected', 'false' );
	} );

	test( 'active tab has tabindex=0', async ( { page } ) => {
		const tab1 = page.locator( '[role="tab"]' ).first();
		await expect( tab1 ).toHaveAttribute( 'tabindex', '0' );
	} );

	test( 'inactive tabs have tabindex=-1', async ( { page } ) => {
		const tab2 = page.locator( '[role="tab"]' ).nth( 1 );
		await expect( tab2 ).toHaveAttribute( 'tabindex', '-1' );
	} );

	test( 'tab aria-controls points to matching panel id', async ( {
		page,
	} ) => {
		const tab1 = page.locator( '[role="tab"]' ).first();
		const controlsId = await tab1.getAttribute( 'aria-controls' );
		expect( controlsId ).toBeTruthy();
		const panel = page.locator( `#${ controlsId }` );
		await expect( panel ).toHaveAttribute( 'role', 'tabpanel' );
	} );

	test( 'panel aria-labelledby points to matching tab id', async ( {
		page,
	} ) => {
		const panel = page.locator( '[role="tabpanel"]' ).first();
		const labelledBy = await panel.getAttribute( 'aria-labelledby' );
		expect( labelledBy ).toBeTruthy();
		const tab = page.locator( `#${ labelledBy }` );
		await expect( tab ).toHaveAttribute( 'role', 'tab' );
	} );

	test( 'tablist has aria-orientation', async ( { page } ) => {
		const tablist = page.locator( '[role="tablist"]' );
		await expect( tablist ).toHaveAttribute(
			'aria-orientation',
			'horizontal'
		);
	} );

	test( 'clicking tab activates it', async ( { page } ) => {
		const tab2 = page.locator( '[role="tab"]' ).nth( 1 );
		await tab2.click();

		await expect( tab2 ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( tab2 ).toHaveAttribute( 'tabindex', '0' );
	} );

	test( 'clicking tab shows its panel', async ( { page } ) => {
		const tab2 = page.locator( '[role="tab"]' ).nth( 1 );
		await tab2.click();

		const panel2 = page.locator( '#ui-tabpanel-tab2' );
		await expect( panel2 ).toBeVisible();
		await expect( panel2 ).toContainText( 'Pricing content here.' );
	} );

	test( 'clicking tab hides previous panel', async ( { page } ) => {
		const tab2 = page.locator( '[role="tab"]' ).nth( 1 );
		await tab2.click();

		const panel1 = page.locator( '#ui-tabpanel-tab1' );
		await expect( panel1 ).toBeHidden();
	} );

	test( 'clicking tab deactivates previous tab', async ( { page } ) => {
		const tab1 = page.locator( '[role="tab"]' ).first();
		const tab2 = page.locator( '[role="tab"]' ).nth( 1 );
		await tab2.click();

		await expect( tab1 ).toHaveAttribute( 'aria-selected', 'false' );
		await expect( tab1 ).toHaveAttribute( 'tabindex', '-1' );
	} );

	test( 'clicking already active tab keeps it active', async ( {
		page,
	} ) => {
		const tab1 = page.locator( '[role="tab"]' ).first();
		await tab1.click();

		await expect( tab1 ).toHaveAttribute( 'aria-selected', 'true' );
		const panel1 = page.locator( '#ui-tabpanel-tab1' );
		await expect( panel1 ).toBeVisible();
	} );

	test( 'ArrowRight moves focus and activates next tab', async ( {
		page,
	} ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.first().focus();
		await page.keyboard.press( 'ArrowRight' );

		await expect( tabs.nth( 1 ) ).toBeFocused();
		await expect( tabs.nth( 1 ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'ArrowLeft moves focus and activates previous tab', async ( {
		page,
	} ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.nth( 1 ).click();
		await tabs.nth( 1 ).focus();
		await page.keyboard.press( 'ArrowLeft' );

		await expect( tabs.first() ).toBeFocused();
		await expect( tabs.first() ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'ArrowRight wraps from last to first', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.nth( 2 ).click();
		await tabs.nth( 2 ).focus();
		await page.keyboard.press( 'ArrowRight' );

		await expect( tabs.first() ).toBeFocused();
		await expect( tabs.first() ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'ArrowLeft wraps from first to last', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.first().focus();
		await page.keyboard.press( 'ArrowLeft' );

		await expect( tabs.nth( 2 ) ).toBeFocused();
		await expect( tabs.nth( 2 ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'Home moves focus to first tab', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.nth( 2 ).click();
		await tabs.nth( 2 ).focus();
		await page.keyboard.press( 'Home' );

		await expect( tabs.first() ).toBeFocused();
		await expect( tabs.first() ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'End moves focus to last tab', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.first().focus();
		await page.keyboard.press( 'End' );

		await expect( tabs.nth( 2 ) ).toBeFocused();
		await expect( tabs.nth( 2 ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );

	test( 'Enter activates focused tab', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.nth( 1 ).focus();
		await page.keyboard.press( 'Enter' );

		await expect( tabs.nth( 1 ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		const panel2 = page.locator( '#ui-tabpanel-tab2' );
		await expect( panel2 ).toBeVisible();
	} );

	test( 'Space activates focused tab', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.nth( 2 ).focus();
		await page.keyboard.press( 'Space' );

		await expect( tabs.nth( 2 ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		const panel3 = page.locator( '#ui-tabpanel-tab3' );
		await expect( panel3 ).toBeVisible();
	} );

	test( 'only active tab is in tab order', async ( { page } ) => {
		const tabs = page.locator( '[role="tab"]' );
		await expect( tabs.first() ).toHaveAttribute( 'tabindex', '0' );
		await expect( tabs.nth( 1 ) ).toHaveAttribute( 'tabindex', '-1' );
		await expect( tabs.nth( 2 ) ).toHaveAttribute( 'tabindex', '-1' );
	} );

	test( 'panel content updates when switching tabs', async ( {
		page,
	} ) => {
		await expect(
			page.locator( '[role="tabpanel"]:not([hidden])' )
		).toContainText( 'Features content here.' );
		await page.locator( '[role="tab"]' ).nth( 1 ).click();
		await expect(
			page.locator( '[role="tabpanel"]:not([hidden])' )
		).toContainText( 'Pricing content here.' );
		await page.locator( '[role="tab"]' ).nth( 2 ).click();
		await expect(
			page.locator( '[role="tabpanel"]:not([hidden])' )
		).toContainText( 'FAQ content here.' );
	} );

	test( 'rapid clicking between tabs works correctly', async ( {
		page,
	} ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.nth( 1 ).click();
		await tabs.nth( 2 ).click();
		await tabs.nth( 0 ).click();
		await tabs.nth( 2 ).click();

		await expect( tabs.nth( 2 ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		const panel3 = page.locator( '#ui-tabpanel-tab3' );
		await expect( panel3 ).toBeVisible();
		const visiblePanels = page.locator(
			'[role="tabpanel"]:not([hidden])'
		);
		await expect( visiblePanels ).toHaveCount( 1 );
	} );

	test( 'rapid keyboard navigation works correctly', async ( {
		page,
	} ) => {
		const tabs = page.locator( '[role="tab"]' );
		await tabs.first().focus();

		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await expect( tabs.first() ).toBeFocused();
		await expect( tabs.first() ).toHaveAttribute(
			'aria-selected',
			'true'
		);
	} );
} );
