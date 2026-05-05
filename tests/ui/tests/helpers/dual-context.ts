import { Page, Locator, expect } from '@playwright/test';

export type TestContext = {
	name: string;
	locator: ( selector: string ) => Locator;
	page: Page;
	reload: () => Promise<void>;
	waitForTimeout: ( ms: number ) => Promise<void>;
};

async function loginToAdmin( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	const loginField = page.locator( '#user_login' );
	if ( await loginField.isVisible( { timeout: 3000 } ).catch( () => false ) ) {
		await loginField.fill( 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForTimeout( 3000 );
	}
}

const editorPageCache: Record<string, string> = {};

export function createContexts( pageSlug: string, rootSelector: string ) {
	const frontendUrl = `/${ pageSlug }/`;

	async function setupFrontend( page: Page ): Promise<TestContext> {
		await page.goto( frontendUrl );
		await page.waitForSelector( rootSelector );
		await page.waitForLoadState( 'networkidle' );

		return {
			name: 'frontend',
			locator: ( sel ) => page.locator( sel ),
			page,
			async reload() {
				await page.reload();
				await page.waitForSelector( rootSelector );
			},
			waitForTimeout: ( ms ) => page.waitForTimeout( ms ),
		};
	}

	async function setupEditor( page: Page ): Promise<TestContext> {
		await loginToAdmin( page );

		if ( ! editorPageCache[ pageSlug ] ) {
			const res = await page.goto( `/wp-json/wp/v2/pages?slug=${ pageSlug }&_fields=id` );
			const json = await res!.json();
			editorPageCache[ pageSlug ] = `/wp-admin/post.php?post=${ json[ 0 ].id }&action=edit`;
		}

		await page.goto( editorPageCache[ pageSlug ] );

		const closeModal = page.locator( '.components-modal__header button[aria-label="Close"]' );
		if ( await closeModal.isVisible( { timeout: 3000 } ).catch( () => false ) ) {
			await closeModal.click();
			await page.waitForTimeout( 500 );
		}

		const frame = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( frame.locator( rootSelector ).first() ).toBeVisible( { timeout: 15000 } );

		return {
			name: 'editor',
			locator: ( sel ) => frame.locator( sel ),
			page,
			async reload() {
				await page.reload();
				const modal = page.locator( '.components-modal__header button[aria-label="Close"]' );
				if ( await modal.isVisible( { timeout: 3000 } ).catch( () => false ) ) {
					await modal.click();
					await page.waitForTimeout( 500 );
				}
				await expect( frame.locator( rootSelector ).first() ).toBeVisible( { timeout: 15000 } );
			},
			waitForTimeout: ( ms ) => page.waitForTimeout( ms * 3 ),
		};
	}

	return [
		{ name: 'frontend', setup: setupFrontend },
		{ name: 'editor', setup: setupEditor },
	];
}
