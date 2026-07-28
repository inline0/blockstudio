import { test, expect, type Locator } from '@playwright/test';

const PAGE = '/select-precision-test/';
const OVERLAY_ROOT = '[data-bsui-select-root]:has(input[name="picked"])';
const PLAIN_ROOT = '[data-bsui-select-root]:has(input[name="plain"])';

type TextMetrics = {
	left: number;
	top: number;
	fontSize: string;
	text: string;
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
			text: ( node.textContent || '' ).trim(),
		};
	} );
}

async function expectTextOverlay( trigger: Locator, option: Locator ) {
	const triggerText = await textRect( trigger );
	const optionText = await textRect( option );

	expect( triggerText, 'trigger must render label text' ).not.toBeNull();
	expect( optionText, 'selected option must render label text' ).not.toBeNull();

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
}

test.describe( 'bsui/select precision', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await page.waitForSelector( '[data-bsui-select-root]' );
	} );

	test( 'selected option label text overlays the trigger label text', async ( {
		page,
	} ) => {
		const root = page.locator( OVERLAY_ROOT );
		const trigger = root.locator( '[data-bsui-select-trigger]' );
		const listbox = root.locator( '[role="listbox"]' );

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await page.waitForTimeout( 350 );

		await expect( listbox ).toHaveCSS( 'position', 'fixed' );

		const selected = root.locator(
			'[role="option"][aria-selected="true"]'
		);
		await expect( selected ).toHaveCount( 1 );
		await expect( selected ).toContainText( 'Cherry' );

		await expectTextOverlay( trigger, selected );
	} );

	test( 'reopening after selecting keeps the overlay exact', async ( {
		page,
	} ) => {
		const root = page.locator( OVERLAY_ROOT );
		const trigger = root.locator( '[data-bsui-select-trigger]' );
		const listbox = root.locator( '[role="listbox"]' );

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await root
			.locator( '[role="option"]' )
			.filter( { hasText: 'Grape' } )
			.click();
		await expect( listbox ).toBeHidden();
		await expect( trigger ).toContainText( 'Grape' );

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await page.waitForTimeout( 350 );

		const selected = root.locator(
			'[role="option"][aria-selected="true"]'
		);
		await expect( selected ).toHaveCount( 1 );
		await expect( selected ).toContainText( 'Grape' );

		await expectTextOverlay( trigger, selected );
	} );

	test( 'the selection check renders on the right edge of the option', async ( {
		page,
	} ) => {
		const root = page.locator( OVERLAY_ROOT );
		await root.locator( '[data-bsui-select-trigger]' ).click();
		await expect( root.locator( '[role="listbox"]' ) ).toBeVisible();
		await page.waitForTimeout( 350 );

		const selected = root.locator(
			'[role="option"][aria-selected="true"]'
		);
		const geometry = await selected.evaluate( ( el ) => {
			const before = getComputedStyle( el, '::before' );
			const style = getComputedStyle( el );
			return {
				content: before.content,
				right: before.right,
				left: before.left,
				paddingLeft: parseFloat( style.paddingLeft ),
				paddingRight: parseFloat( style.paddingRight ),
			};
		} );

		expect( geometry.content, 'selected option must draw a check' ).not.toBe(
			'none'
		);
		expect(
			parseFloat( geometry.right ),
			`check right ${ geometry.right } must equal the control padding`
		).toBeCloseTo( 12, 0 );
		if ( geometry.left !== 'auto' ) {
			expect(
				parseFloat( geometry.left ),
				`check left ${ geometry.left } must sit far from the left edge`
			).toBeGreaterThan( parseFloat( geometry.right ) );
		}
		expect(
			geometry.paddingRight,
			`padding-right ${ geometry.paddingRight } must exceed padding-left ${ geometry.paddingLeft }`
		).toBeGreaterThan( geometry.paddingLeft );
		expect( geometry.paddingLeft ).toBeCloseTo( 12, 0 );
	} );

	test( 'a select without a value opens below its trigger', async ( {
		page,
	} ) => {
		const root = page.locator( PLAIN_ROOT );
		const trigger = root.locator( '[data-bsui-select-trigger]' );
		const listbox = root.locator( '[role="listbox"]' );

		await trigger.click();
		await expect( listbox ).toBeVisible();
		await page.waitForTimeout( 350 );

		await expect( listbox ).toHaveCSS( 'position', 'fixed' );

		const triggerBox = await trigger.boundingBox();
		const listboxBox = await listbox.boundingBox();
		expect( triggerBox ).not.toBeNull();
		expect( listboxBox ).not.toBeNull();

		const triggerBottom =
			( triggerBox as { y: number; height: number } ).y +
			( triggerBox as { y: number; height: number } ).height;
		expect(
			( listboxBox as { y: number } ).y,
			`listbox top ${ ( listboxBox as { y: number } ).y } must sit below trigger bottom ${ triggerBottom }`
		).toBeGreaterThanOrEqual( triggerBottom );
	} );
} );
