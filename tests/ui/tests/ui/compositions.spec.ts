import { test, expect, type Locator, type Page } from '@playwright/test';

async function box( locator: Locator ) {
	const rect = await locator.boundingBox();
	expect( rect ).not.toBeNull();
	return rect as { x: number; y: number; width: number; height: number };
}

function bodyOverflow( page: Page ) {
	return page.evaluate( () => document.body.style.overflow );
}

type TextMetrics = {
	left: number;
	top: number;
	fontSize: string;
};

async function textRect( locator: Locator ): Promise< TextMetrics | null > {
	return locator.evaluate( ( el ) => {
		const walker = document.createTreeWalker( el, NodeFilter.SHOW_TEXT, {
			acceptNode: ( node ) =>
				( node.textContent || '' ).trim().length > 0
					? NodeFilter.FILTER_ACCEPT
					: NodeFilter.FILTER_REJECT,
		} );
		const node = walker.nextNode();
		if ( ! node ) return null;
		const range = document.createRange();
		range.selectNodeContents( node );
		const rect = range.getBoundingClientRect();
		return {
			left: rect.left,
			top: rect.top,
			fontSize: getComputedStyle( el ).fontSize,
		};
	} );
}

async function expectAnchored( trigger: Locator, popup: Locator ) {
	await expect( popup ).toHaveCSS( 'position', 'fixed' );

	const selected = popup.locator( '[role="option"][aria-selected="true"]' );

	if ( ( await selected.count() ) === 1 ) {
		const triggerText = await textRect( trigger );
		const optionText = await textRect( selected );
		expect( triggerText, 'trigger must render label text' ).not.toBeNull();
		expect(
			optionText,
			'selected option must render label text'
		).not.toBeNull();

		const a = triggerText as TextMetrics;
		const b = optionText as TextMetrics;
		expect(
			Math.abs( b.left - a.left ),
			`option text left ${ b.left } drifts from trigger text left ${ a.left }`
		).toBeLessThanOrEqual( 1 );
		expect(
			Math.abs( b.top - a.top ),
			`option text top ${ b.top } drifts from trigger text top ${ a.top }`
		).toBeLessThanOrEqual( 1 );
		expect( b.fontSize, 'popup text must match trigger font size' ).toBe(
			a.fontSize
		);
		return;
	}

	const triggerBox = await box( trigger );
	const popupBox = await box( popup );
	const triggerBottom = triggerBox.y + triggerBox.height;

	expect(
		popupBox.y,
		`popup top ${ popupBox.y } must sit below trigger bottom ${ triggerBottom }`
	).toBeGreaterThanOrEqual( triggerBottom );
	expect(
		popupBox.y - triggerBottom,
		`popup top ${ popupBox.y } sits far below trigger bottom ${ triggerBottom }`
	).toBeLessThanOrEqual( 24 );
	expect(
		Math.abs( popupBox.x - triggerBox.x ),
		`popup left ${ popupBox.x } drifts from trigger left ${ triggerBox.x }`
	).toBeLessThanOrEqual( 8 );
}

async function expectWithinViewport( page: Page, locator: Locator ) {
	const viewport = page.viewportSize();
	expect( viewport ).not.toBeNull();
	const { width, height } = viewport as { width: number; height: number };
	const rect = await box( locator );

	expect( rect.x, `left edge ${ rect.x } leaves the viewport` ).toBeGreaterThanOrEqual( 0 );
	expect( rect.y, `top edge ${ rect.y } leaves the viewport` ).toBeGreaterThanOrEqual( 0 );
	expect(
		rect.x + rect.width,
		`right edge ${ rect.x + rect.width } leaves the viewport`
	).toBeLessThanOrEqual( width );
	expect(
		rect.y + rect.height,
		`bottom edge ${ rect.y + rect.height } leaves the viewport`
	).toBeLessThanOrEqual( height );
}

async function openDialog( page: Page ) {
	await page.locator( '[data-bsui-dialog-trigger] button' ).first().click();
	await expect( page.locator( '[data-bsui-dialog-popup]' ).first() ).toBeVisible();
	await page.waitForTimeout( 300 );
}

async function openDrawer( page: Page ) {
	await page.locator( '[data-bsui-drawer-trigger] button' ).click();
	await expect( page.locator( '[data-bsui-drawer-popup]' ) ).toBeVisible();
	// The drawer slides for 450ms; the popup must be untransformed before a
	// fixed floating popup opened inside it can anchor correctly.
	await page.waitForTimeout( 500 );
}

test.describe( 'composition: dialog-select', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-select-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'listbox anchors to its trigger inside the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await page.waitForTimeout( 300 );

		await expectAnchored( trigger, listbox );
	} );

	test( 'selecting an option keeps the dialog open', async ( { page } ) => {
		await openDialog( page );

		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();

		const option = page
			.locator( '[data-bsui-select-root] [role="option"]' )
			.filter( { hasText: 'Cherry' } );
		await option.click();

		await expect( listbox ).toBeHidden();
		await expect( trigger ).toContainText( 'Cherry' );
		await expect( page.locator( '[data-bsui-dialog-popup]' ) ).toBeVisible();
	} );

	test( 'Escape closes the listbox first, then the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const popup = page.locator( '[data-bsui-dialog-popup]' );
		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await expect(
			page
				.locator( '[data-bsui-select-root] [role="option"]' )
				.filter( { hasText: 'Banana' } )
		).toBeFocused();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.keyboard.press( 'Escape' );
		await expect( listbox ).toBeHidden();
		await expect( popup ).toBeVisible();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.waitForTimeout( 300 );
		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();

		await page.waitForTimeout( 300 );
		expect( await bodyOverflow( page ) ).toBe( '' );
	} );
} );

test.describe( 'composition: dialog-menu', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-menu-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'menu opens inside the dialog and stays in the viewport', async ( {
		page,
	} ) => {
		await openDialog( page );

		const menu = page.locator( '[data-bsui-menu-root] [role="menu"]' );
		await page.locator( '[data-bsui-menu-trigger] button' ).click();
		await expect( menu ).toBeVisible();
		await page.waitForTimeout( 300 );

		await expectWithinViewport( page, menu );
	} );

	test( 'item click closes the menu but not the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const menu = page.locator( '[data-bsui-menu-root] [role="menu"]' );
		await page.locator( '[data-bsui-menu-trigger] button' ).click();
		await expect( menu ).toBeVisible();
		await page.waitForTimeout( 300 );

		const item = page
			.locator( '[data-bsui-menu-root] [role="menuitem"]' )
			.filter( { hasText: 'Edit' } );
		await item.click();

		await expect( menu ).toBeHidden();
		await expect( page.locator( '[data-bsui-dialog-popup]' ) ).toBeVisible();
	} );

	test( 'Escape closes the menu first, then the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const popup = page.locator( '[data-bsui-dialog-popup]' );
		const menu = page.locator( '[data-bsui-menu-root] [role="menu"]' );

		await page.locator( '[data-bsui-menu-trigger] button' ).click();
		await expect( menu ).toBeVisible();
		await page.waitForTimeout( 300 );
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.keyboard.press( 'Escape' );
		await expect( menu ).toBeHidden();
		await expect( popup ).toBeVisible();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.waitForTimeout( 300 );
		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();

		await page.waitForTimeout( 300 );
		expect( await bodyOverflow( page ) ).toBe( '' );
	} );
} );

test.describe( 'composition: dialog-combobox', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-combobox-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'typing filters the options', async ( { page } ) => {
		await openDialog( page );

		const input = page.locator( '[data-bsui-combobox-input]' );
		const popup = page.locator( '[data-bsui-combobox-popup]' );
		const visibleOptions = page.locator(
			'[data-bsui-combobox-root] [role="option"]:not([hidden])'
		);

		await input.click();
		await expect( popup ).toBeVisible();
		await expect( visibleOptions ).toHaveCount( 4 );

		await input.fill( 'rem' );
		await expect( visibleOptions ).toHaveCount( 1 );
		await expect( visibleOptions.first() ).toContainText( 'Remix' );
	} );

	test( 'Enter selects and keeps the dialog open', async ( { page } ) => {
		await openDialog( page );

		const input = page.locator( '[data-bsui-combobox-input]' );
		const popup = page.locator( '[data-bsui-combobox-popup]' );

		await input.click();
		await expect( popup ).toBeVisible();

		await input.fill( 'rem' );
		await expect(
			page.locator(
				'[data-bsui-combobox-root] [role="option"]:not([hidden])'
			)
		).toHaveCount( 1 );

		await page.keyboard.press( 'ArrowDown' );
		await input.click();
		await page.keyboard.press( 'Enter' );

		await expect( input ).toHaveValue( 'Remix' );
		await expect( popup ).toBeHidden();
		await expect( page.locator( '[data-bsui-dialog-popup]' ) ).toBeVisible();
	} );

	test( 'option list anchors under the input, clear of the dialog header', async ( {
		page,
	} ) => {
		await openDialog( page );

		const heading = page.locator( '[data-bsui-dialog-popup] h3' );
		const input = page.locator( '[data-bsui-combobox-input]' );
		const popup = page.locator( '[data-bsui-combobox-popup]' );

		await input.click();
		await expect( popup ).toBeVisible();
		await input.fill( 'n' );
		await expect(
			page
				.locator(
					'[data-bsui-combobox-root] [role="option"]:not([hidden])'
				)
				.first()
		).toBeVisible();
		await page.waitForTimeout( 300 );

		await expect( popup ).toHaveCSS( 'position', 'fixed' );

		const headingBox = await box( heading );
		const inputBox = await box( input );
		const popupBox = await box( popup );
		const inputBottom = inputBox.y + inputBox.height;

		expect(
			Math.abs( popupBox.x - inputBox.x ),
			`popup left ${ popupBox.x } drifts from input left ${ inputBox.x }`
		).toBeLessThanOrEqual( 8 );
		expect(
			popupBox.y - inputBottom,
			`popup top ${ popupBox.y } sits above input bottom ${ inputBottom }`
		).toBeGreaterThanOrEqual( 0 );
		expect(
			popupBox.y - inputBottom,
			`popup top ${ popupBox.y } sits far below input bottom ${ inputBottom }`
		).toBeLessThanOrEqual( 24 );
		expect(
			popupBox.y,
			`popup top ${ popupBox.y } overlaps the header ending at ${ headingBox.y + headingBox.height }`
		).toBeGreaterThanOrEqual( headingBox.y + headingBox.height );
	} );
} );

test.describe( 'composition: dialog-close-variants', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-close-variants-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'close buttons carry their declared variants', async ( { page } ) => {
		await openDialog( page );

		const closeButtons = page.locator( '[data-bsui-dialog-close] button' );
		await expect( closeButtons ).toHaveCount( 2 );
		await expect( closeButtons.nth( 0 ) ).toHaveAttribute(
			'data-variant',
			'outline'
		);
		await expect( closeButtons.nth( 0 ) ).toContainText( 'Cancel' );
		await expect( closeButtons.nth( 1 ) ).toHaveAttribute(
			'data-variant',
			'default'
		);
		await expect( closeButtons.nth( 1 ) ).toContainText( 'Save' );
	} );

	test( 'the default variant close still closes the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const popup = page.locator( '[data-bsui-dialog-popup]' );
		await popup
			.locator( '[data-bsui-dialog-close] button' )
			.filter( { hasText: 'Save' } )
			.click();
		await expect( popup ).toBeHidden();
	} );

	test( 'an icon-sm button renders as a 2rem square', async ( { page } ) => {
		const button = page.locator( '[data-bsui-button][data-size="icon-sm"]' );
		await expect( button ).toBeVisible();

		const rect = await box( button );
		expect( rect.width, `icon-sm width ${ rect.width }` ).toBeCloseTo(
			32,
			0
		);
		expect( rect.height, `icon-sm height ${ rect.height }` ).toBeCloseTo(
			32,
			0
		);
	} );
} );

test.describe( 'composition: toolbar spacing', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/toolbar-test/' );
		await page.waitForSelector( '[role="toolbar"]' );
	} );

	test( 'consecutive toolbar children sit at one even gap', async ( {
		page,
	} ) => {
		const gaps = await page.evaluate( () => {
			const toolbar = document.querySelector( '[role="toolbar"]' );
			if ( ! toolbar ) return null;

			const items: Element[] = [];
			const collect = ( el: Element ) => {
				for ( const child of el.children ) {
					if ( getComputedStyle( child ).display === 'contents' ) {
						collect( child );
						continue;
					}
					if ( child.getBoundingClientRect().width > 0 ) {
						items.push( child );
					}
				}
			};
			collect( toolbar );

			const rects = items
				.map( ( item ) => item.getBoundingClientRect() )
				.sort( ( a, b ) => a.left - b.left );
			const result: number[] = [];
			for ( let i = 1; i < rects.length; i += 1 ) {
				result.push( rects[ i ].left - rects[ i - 1 ].right );
			}
			return result;
		} );

		expect( gaps, 'toolbar must render flex items' ).not.toBeNull();
		const values = gaps as number[];
		expect( values.length ).toBeGreaterThanOrEqual( 4 );

		const spread = Math.max( ...values ) - Math.min( ...values );
		expect(
			spread,
			`gaps ${ values.map( ( gap ) => gap.toFixed( 2 ) ).join( ', ' ) } must be even`
		).toBeLessThanOrEqual( 1 );
	} );
} );

test.describe( 'composition: drawer-select', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/drawer-select-test/' );
		await page.waitForSelector( '[data-bsui-drawer-root]' );
	} );

	test( 'listbox anchors to its trigger inside the drawer', async ( {
		page,
	} ) => {
		await openDrawer( page );

		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await page.waitForTimeout( 300 );

		await expectAnchored( trigger, listbox );
	} );

	test( 'Escape closes the listbox first, then the drawer', async ( {
		page,
	} ) => {
		await openDrawer( page );

		const popup = page.locator( '[data-bsui-drawer-popup]' );
		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await expect(
			page.locator( '[data-bsui-select-root] [role="option"]' ).first()
		).toBeFocused();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.keyboard.press( 'Escape' );
		await expect( listbox ).toBeHidden();
		await expect( popup ).toBeVisible();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.waitForTimeout( 300 );
		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();

		await page.waitForTimeout( 500 );
		expect( await bodyOverflow( page ) ).toBe( '' );
	} );
} );

test.describe( 'composition: dialog-tooltip', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-tooltip-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'tooltip shows over the footer button inside the viewport', async ( {
		page,
	} ) => {
		await openDialog( page );

		const saveButton = page.locator( '[data-bsui-tooltip-trigger] button' );
		const tooltip = page.locator( '[role="tooltip"]' );

		await saveButton.hover();
		await expect( tooltip ).toBeVisible();
		await page.waitForTimeout( 300 );

		await expectWithinViewport( page, tooltip );
	} );

	test( 'Escape closes the tooltip layer before the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const popup = page.locator( '[data-bsui-dialog-popup]' );
		const saveButton = page.locator( '[data-bsui-tooltip-trigger] button' );
		const tooltip = page.locator( '[role="tooltip"]' );

		await saveButton.hover();
		await saveButton.focus();
		await expect( tooltip ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( tooltip ).toBeHidden();
		await expect( popup ).toBeVisible();

		// The tooltip trigger swallows Escape while focused, so move focus to
		// the close button before asking the dialog to close.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Escape' );
		await expect( popup ).toBeHidden();

		await page.waitForTimeout( 300 );
		expect( await bodyOverflow( page ) ).toBe( '' );
	} );
} );

test.describe( 'composition: drawer-alert', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/drawer-alert-test/' );
		await page.waitForSelector( '[data-bsui-drawer-root]' );
	} );

	test( 'alert opens above the drawer', async ( { page } ) => {
		await openDrawer( page );

		const alert = page.locator( '[role="alertdialog"]' );
		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		await expect( alert ).toBeVisible();
		await page.waitForTimeout( 300 );

		const alertBox = await box( alert );
		const centre = {
			x: alertBox.x + alertBox.width / 2,
			y: alertBox.y + alertBox.height / 2,
		};
		const onTop = await page.evaluate( ( point ) => {
			const el = document.elementFromPoint( point.x, point.y );
			return !! el?.closest( '[data-bsui-alert-dialog-popup]' );
		}, centre );
		expect( onTop, 'alert centre must resolve inside the alert popup' ).toBe(
			true
		);
	} );

	test( 'backdrop click does not close the alert', async ( { page } ) => {
		await openDrawer( page );

		const alert = page.locator( '[role="alertdialog"]' );
		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		await expect( alert ).toBeVisible();

		await page.mouse.click( 12, 12 );
		await page.waitForTimeout( 300 );
		await expect( alert ).toBeVisible();
		await expect( page.locator( '[data-bsui-drawer-popup]' ) ).toBeVisible();
	} );

	test( 'alert close returns to the drawer, drawer close unlocks', async ( {
		page,
	} ) => {
		await openDrawer( page );

		const drawerPopup = page.locator( '[data-bsui-drawer-popup]' );
		const alert = page.locator( '[role="alertdialog"]' );

		await page.locator( '[data-bsui-alert-dialog-trigger] button' ).click();
		await expect( alert ).toBeVisible();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.locator( '[data-bsui-alert-dialog-close] button' ).click();
		await expect( alert ).toBeHidden();
		await expect( drawerPopup ).toBeVisible();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.locator( '[data-bsui-drawer-close] button' ).click();
		await expect( drawerPopup ).toBeHidden();

		await page.waitForTimeout( 500 );
		expect( await bodyOverflow( page ) ).toBe( '' );
	} );
} );

test.describe( 'composition: dialog-in-dialog-select', () => {
	async function openStack( page: Page ) {
		const outerTrigger = page.locator( '[data-bsui-dialog-trigger]' ).first();
		await outerTrigger.locator( 'button' ).click();

		const outerDialog = page
			.locator( '[role="dialog"]:not([hidden])' )
			.first();
		await expect( outerDialog ).toBeVisible();

		const innerTrigger = outerDialog.locator( '[data-bsui-dialog-trigger]' );
		await innerTrigger.locator( 'button' ).click();

		await expect(
			page.locator( '[role="dialog"]:not([hidden])' )
		).toHaveCount( 2 );
		await page.waitForTimeout( 300 );
	}

	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-in-dialog-select-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'listbox anchors inside the top dialog of the stack', async ( {
		page,
	} ) => {
		await openStack( page );

		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await page.waitForTimeout( 300 );

		await expectAnchored( trigger, listbox );
	} );

	test( 'Escape unwinds listbox, inner dialog, outer dialog', async ( {
		page,
	} ) => {
		await openStack( page );

		const dialogs = page.locator( '[role="dialog"]:not([hidden])' );
		const trigger = page.locator( '[data-bsui-select-trigger]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await expect(
			page.locator( '[data-bsui-select-root] [role="option"]' ).first()
		).toBeFocused();
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.keyboard.press( 'Escape' );
		await expect( listbox ).toBeHidden();
		await expect( dialogs ).toHaveCount( 2 );
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.waitForTimeout( 300 );
		await page.keyboard.press( 'Escape' );
		await expect( dialogs ).toHaveCount( 1 );
		expect( await bodyOverflow( page ) ).toBe( 'hidden' );

		await page.waitForTimeout( 300 );
		await page.keyboard.press( 'Escape' );
		await expect( dialogs ).toHaveCount( 0 );

		await page.waitForTimeout( 300 );
		expect( await bodyOverflow( page ) ).toBe( '' );
	} );
} );

test.describe( 'composition: popover-calendar', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/popover-calendar-test/' );
		await page.waitForSelector( '[data-bsui-popover-root]' );
	} );

	test( 'day selection keeps the popover open', async ( { page } ) => {
		const popup = page.locator( '[data-bsui-popover-root] [role="dialog"]' );

		await page.locator( '[data-bsui-popover-trigger] button' ).click();
		await expect( popup ).toBeVisible();
		await expect( page.locator( '[data-bsui-calendar]' ) ).toBeVisible();

		const day = popup.getByRole( 'button', { name: '15', exact: true } );
		await day.click();

		await expect( day ).toHaveAttribute( 'data-selected', '' );
		await expect( popup ).toBeVisible();
	} );

	test( 'outside click closes the popover', async ( { page } ) => {
		const popup = page.locator( '[data-bsui-popover-root] [role="dialog"]' );

		await page.locator( '[data-bsui-popover-trigger] button' ).click();
		await expect( popup ).toBeVisible();

		await page.click( 'body', { position: { x: 10, y: 10 } } );
		await expect( popup ).toBeHidden();
	} );
} );

test.describe( 'composition: context-menu-dialog', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/context-menu-dialog-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'menu opens at the pointer inside the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const paragraph = page.locator( '[data-bsui-context-menu-root] p' );
		const menu = page.locator(
			'[data-bsui-context-menu-root] [role="menu"]'
		);

		const paragraphBox = await box( paragraph );
		const pointer = { x: paragraphBox.x + 40, y: paragraphBox.y + 12 };
		await paragraph.click( {
			button: 'right',
			position: { x: 40, y: 12 },
		} );

		await expect( menu ).toBeVisible();
		await page.waitForTimeout( 300 );

		const menuBox = await box( menu );
		expect(
			Math.abs( menuBox.x - pointer.x ),
			`menu left ${ menuBox.x } sits far from pointer x ${ pointer.x }`
		).toBeLessThanOrEqual( 40 );
		expect(
			Math.abs( menuBox.y - pointer.y ),
			`menu top ${ menuBox.y } sits far from pointer y ${ pointer.y }`
		).toBeLessThanOrEqual( 40 );
	} );

	test( 'item click closes the menu but not the dialog', async ( {
		page,
	} ) => {
		await openDialog( page );

		const paragraph = page.locator( '[data-bsui-context-menu-root] p' );
		const menu = page.locator(
			'[data-bsui-context-menu-root] [role="menu"]'
		);

		await paragraph.click( {
			button: 'right',
			position: { x: 40, y: 12 },
		} );
		await expect( menu ).toBeVisible();
		await page.waitForTimeout( 300 );

		const item = page
			.locator( '[data-bsui-context-menu-root] [role="menuitem"]' )
			.filter( { hasText: 'Copy' } );
		await item.click();

		await expect( menu ).toBeHidden();
		await expect( page.locator( '[data-bsui-dialog-popup]' ) ).toBeVisible();
	} );
} );

test.describe( 'composition: toast-dialog', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/toast-dialog-test/' );
		await page.waitForSelector( '[data-bsui-toast-provider]' );
	} );

	test( 'dialog layers above the page and the toast', async ( { page } ) => {
		const toast = page.locator(
			'[data-bsui-toast-provider] [role="status"]'
		);
		await expect( toast ).toBeVisible();
		await expect( toast ).toContainText( 'Draft saved' );

		await openDialog( page );
		const popup = page.locator( '[data-bsui-dialog-popup]' );

		const zIndex = await popup.evaluate( ( el ) =>
			Number.parseInt( getComputedStyle( el ).zIndex, 10 )
		);
		expect(
			zIndex,
			`dialog popup sits at z-index ${ zIndex }`
		).toBeGreaterThanOrEqual( 51 );

		const popupBox = await box( popup );
		const centre = {
			x: popupBox.x + popupBox.width / 2,
			y: popupBox.y + popupBox.height / 2,
		};
		const onTop = await page.evaluate( ( point ) => {
			const el = document.elementFromPoint( point.x, point.y );
			return !! el?.closest( '[data-bsui-dialog-popup]' );
		}, centre );
		expect(
			onTop,
			'dialog centre must resolve inside the dialog popup'
		).toBe( true );
	} );
} );

test.describe( 'escape order stress', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/dialog-select-test/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );
	} );

	test( 'three rapid escapes settle fully closed and unlocked', async ( {
		page,
	} ) => {
		await openDialog( page );

		const popup = page.locator( '[data-bsui-dialog-popup]' );
		const listbox = page.locator(
			'[data-bsui-select-root] [role="listbox"]'
		);

		await page.locator( '[data-bsui-select-trigger]' ).click();
		await expect( listbox ).toBeVisible();

		for ( let i = 0; i < 3; i += 1 ) {
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 50 );
		}

		await page.waitForTimeout( 500 );
		await expect( listbox ).toBeHidden();
		await expect( popup ).toBeHidden();
		expect( await bodyOverflow( page ) ).toBe( '' );

		const locked = await page.evaluate( () =>
			document.documentElement.hasAttribute( 'data-bs-ui-scroll-locked' )
		);
		expect( locked, 'scroll lock must not stick' ).toBe( false );
	} );
} );
