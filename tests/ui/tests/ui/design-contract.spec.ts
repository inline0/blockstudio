import { test, expect, type Page } from '@playwright/test';

/**
 * The design contract, measured against the fixture theme.
 *
 * The consuming site runs the same assertions against its own tokens, so a
 * drift is caught wherever it starts: a component sheet, the shared tokens,
 * or a theme override. These read computed styles rather than source, which
 * is the only place stylesheet loading order and asset wiring show up.
 */

const MODALS = [
	{ page: '/dialog-test/', trigger: '[data-bsui-dialog-trigger]', popup: '[data-bsui-dialog-popup]' },
	{ page: '/alert-dialog-test/', trigger: '[data-bsui-alert-dialog-trigger]', popup: '[data-bsui-alert-dialog-popup]' },
	{ page: '/drawer-test/', trigger: '[data-bsui-drawer-trigger]', popup: '[data-bsui-drawer-popup]' },
];

async function openModal( page: Page, modal: ( typeof MODALS )[ number ] ) {
	await page.goto( modal.page );
	await page.locator( `${ modal.trigger } button` ).first().click();
	await page.waitForTimeout( 500 );
}

test.describe( 'design contract', () => {
	test( 'every label renders as one primitive', async ( { page } ) => {
		const seen: string[] = [];

		for ( const path of [ '/checkbox-test/', '/switch-test/', '/radio-test/' ] ) {
			await page.goto( path );
			const styles = await page.$$eval( '[data-bsui-label]', ( nodes ) =>
				nodes.map( ( node ) => {
					const style = getComputedStyle( node );
					return [ style.fontSize, style.fontWeight, style.fontFamily ].join( '|' );
				} )
			);
			seen.push( ...styles );
		}

		expect( seen.length ).toBeGreaterThan( 2 );
		expect( new Set( seen ).size, `labels disagree: ${ [ ...new Set( seen ) ].join( ' vs ' ) }` ).toBe( 1 );
	} );

	test( 'every modal shares one surface', async ( { page } ) => {
		const surfaces: Array< Record< string, string > > = [];

		for ( const modal of MODALS ) {
			await openModal( page, modal );
			const surface = await page.evaluate( ( selector ) => {
				const popup = document.querySelector( `${ selector }:not([hidden])` );
				if ( ! popup ) return null;
				const style = getComputedStyle( popup );
				return {
					padding: style.padding,
					background: style.backgroundColor,
					zIndex: style.zIndex,
					gap: style.gap,
				};
			}, modal.popup );

			expect( surface, `${ modal.page } must open` ).not.toBeNull();
			surfaces.push( { page: modal.page, ...( surface as object ) } as Record< string, string > );
		}

		for ( const key of [ 'padding', 'background', 'zIndex' ] ) {
			expect(
				new Set( surfaces.map( ( s ) => s[ key ] ) ).size,
				`${ key } differs: ${ surfaces.map( ( s ) => `${ s.page } ${ s[ key ] }` ).join( ', ' ) }`
			).toBe( 1 );
		}

		expect( surfaces[ 0 ].gap ).toBe( surfaces[ 2 ].gap );
		expect( parseFloat( surfaces[ 1 ].gap ) ).toBeLessThan( parseFloat( surfaces[ 0 ].gap ) );
	} );

	test( 'a modal separates its children', async ( { page } ) => {
		for ( const modal of MODALS ) {
			await openModal( page, modal );
			const seams = await page.evaluate( ( selector ) => {
				const popup = document.querySelector( `${ selector }:not([hidden])` );
				const wrapper = popup?.firstElementChild;
				if ( ! wrapper ) return null;
				const rects = [ ...wrapper.children ]
					.map( ( child ) => child.getBoundingClientRect() )
					.filter( ( rect ) => rect.height > 0 )
					.sort( ( a, b ) => a.top - b.top );
				const gaps: number[] = [];
				for ( let i = 1; i < rects.length; i += 1 ) {
					gaps.push( Math.round( rects[ i ].top - rects[ i - 1 ].bottom ) );
				}
				return gaps;
			}, modal.popup );

			expect( seams, `${ modal.page } must render children` ).not.toBeNull();
			for ( const seam of seams as number[] ) {
				expect( seam, `${ modal.page } children sit ${ seam }px apart` ).toBeGreaterThanOrEqual( 8 );
			}
		}
	} );

	test( 'list popups share one surface', async ( { page } ) => {
		const surfaces: Array< Record< string, string > > = [];

		for ( const [ path, trigger, popup ] of [
			[ '/menu-test/', '[data-bsui-menu-trigger]', '[data-bsui-menu-root] [role="menu"]' ],
			[ '/select-test/', '[data-bsui-select-trigger]', '[data-bsui-select-root] [role="listbox"]' ],
		] as const ) {
			await page.goto( path );
			await page.locator( trigger ).first().click();
			await page.waitForTimeout( 300 );

			const surface = await page.evaluate( ( selector ) => {
				const node = [ ...document.querySelectorAll( selector ) ].find(
					( candidate ) => ! candidate.hasAttribute( 'hidden' )
				);
				if ( ! node ) return null;
				const style = getComputedStyle( node );
				return {
					background: style.backgroundColor,
					radius: style.borderRadius,
					padding: style.padding,
					shadow: style.boxShadow,
				};
			}, popup );

			expect( surface, `${ path } popup must open` ).not.toBeNull();
			surfaces.push( { path, ...( surface as object ) } as Record< string, string > );
		}

		for ( const key of [ 'background', 'radius', 'padding', 'shadow' ] ) {
			expect(
				new Set( surfaces.map( ( s ) => s[ key ] ) ).size,
				`${ key } differs: ${ surfaces.map( ( s ) => `${ s.path } ${ s[ key ] }` ).join( ', ' ) }`
			).toBe( 1 );
		}
	} );
} );
