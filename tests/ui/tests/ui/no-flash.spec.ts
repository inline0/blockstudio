import { test, expect } from '@playwright/test';

test.describe( 'no flash of hidden overlays', () => {
	test( 'overlay elements must not use data-wp-bind--hidden', async ( {
		page,
	} ) => {
		await page.goto( '/showcase/' );
		await page.waitForSelector( '[data-bsui-dialog-root]' );

		const violators = await page.evaluate( () => {
			const found: string[] = [];
			const selectors = [
				'[role="alertdialog"]',
				'[role="dialog"][hidden]',
				'[role="listbox"]',
				'[role="menu"]',
				'[role="tooltip"]',
				'[data-bsui-dialog-backdrop]',
				'[data-bsui-dialog-backdrop][hidden]',
				'[data-bsui-drawer-root] [aria-hidden="true"][hidden]',
				'[data-bsui-alert-dialog-root] [aria-hidden="true"][hidden]',
			];
			for ( const sel of selectors ) {
				document.querySelectorAll( sel ).forEach( ( el ) => {
					if ( el.hasAttribute( 'data-wp-bind--hidden' ) ) {
						const role = el.getAttribute( 'role' ) || '';
						const ns =
							el
								.closest( '[data-wp-interactive]' )
								?.getAttribute( 'data-wp-interactive' ) ||
							'unknown';
						found.push( `${ ns } ${ sel }(${ role })` );
					}
				} );
			}
			return found;
		} );

		expect(
			violators,
			'Overlay elements should not use data-wp-bind--hidden (causes hydration flash). Use manual hidden toggling in JS actions instead.'
		).toEqual( [] );
	} );
} );
