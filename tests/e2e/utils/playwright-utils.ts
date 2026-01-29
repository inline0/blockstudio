import { test as baseTest, expect, Page, FrameLocator, Locator } from '@playwright/test';

/**
 * wp-env E2E Test Utilities
 *
 * Uses worker-scoped fixtures to share page across ALL tests in a worker.
 * This means tests can be serial and share state (e.g., blocks added in one test
 * persist to the next test).
 */

type WorkerFixtures = {
	workerPage: Page;
};

type TestFixtures = {
	editor: Page;
};

// Extend base test with worker-scoped fixtures
export const test = baseTest.extend<TestFixtures, WorkerFixtures>({
	// Worker-scoped: shared across ALL tests in the worker
	workerPage: [async ({ browser }, use) => {
		console.log('\n  [Worker] Initializing shared wp-env page...');

		const page = await browser.newPage();

		// Login to WordPress admin
		await page.goto('http://localhost:8888/wp-login.php');
		await page.waitForLoadState('domcontentloaded');

		// Fill login form with explicit waits
		const userInput = page.locator('input[name="log"]');
		const passInput = page.locator('input[name="pwd"]');

		await userInput.waitFor({ state: 'visible' });
		await userInput.click();
		await userInput.fill('admin');

		await passInput.waitFor({ state: 'visible' });
		await passInput.click();
		await passInput.fill('password');

		await page.locator('input[type="submit"]').click();
		await page.waitForURL(/wp-admin/, { timeout: 30000 });

		// Call E2E setup endpoint to create test data (posts, media, users, terms)
		try {
			const setupResult = await page.evaluate(async () => {
				const res = await fetch('/wp-json/blockstudio-test/v1/e2e/setup', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
				});
				if (!res.ok) {
					return { error: `HTTP ${res.status}: ${await res.text()}` };
				}
				return res.json();
			});

			if (setupResult.error) {
				console.log('  [Worker] E2E Setup error:', setupResult.error);
			} else if (setupResult.message) {
				console.log('  [Worker] E2E Setup:', setupResult.message);
			} else {
				console.log('  [Worker] E2E Setup:', JSON.stringify(setupResult));
			}
		} catch (e) {
			console.log('  [Worker] E2E Setup error:', (e as Error).message);
		}

		// Navigate to editor
		await page.goto('http://localhost:8888/wp-admin/post-new.php');

		// Close welcome guide modal if present
		const welcomeCloseBtn = page.locator('.components-modal__frame button[aria-label="Close"]');
		if (await welcomeCloseBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
			await welcomeCloseBtn.click();
		}

		// Wait for editor to load
		await page.waitForSelector('.is-root-container', { timeout: 30000 });

		console.log('  [Worker] wp-env ready!\n');

		await use(page);

		await page.close();
	}, { scope: 'worker' }],

	// Test-scoped: runs for each test but uses the shared worker page
	editor: async ({ workerPage }, use) => {
		// Close any open modals
		const modalOverlay = await workerPage.locator('.components-modal__screen-overlay').isVisible().catch(() => false);
		if (modalOverlay) {
			const closeBtn = workerPage.locator('.components-modal__frame button[aria-label="Close"]');
			if (await closeBtn.isVisible().catch(() => false)) {
				await closeBtn.click();
				await delay(500);
			}
		}

		await use(workerPage);
	},
});

export { expect };

// Editor type is Page (use canvas() for iframe content)
export type Editor = Page;

/** Get the editor canvas - returns iframe content or page if no iframe */
export const canvas = (page: Page): FrameLocator | Page => {
	// Check if iframe exists synchronously (for selector building)
	// This is a workaround - ideally we'd detect this once at setup
	const iframe = page.frameLocator('iframe[name="editor-canvas"]');
	return iframe;
};

/** Check if there's an editor iframe (for conditional logic) */
export const hasEditorIframe = async (page: Page): Promise<boolean> => {
	return await page.locator('iframe[name="editor-canvas"]').isVisible({ timeout: 1000 }).catch(() => false);
};

/** Delay helper */
export const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/** Click on an element */
export const click = async (page: Page, selector: string) => {
	await page.locator(selector).click();
};

/** Fill an input */
export const fill = async (page: Page, selector: string, value: string) => {
	const input = page.locator(selector);
	await input.fill(value);
};

/** Type text character by character */
export const type = async (page: Page, selector: string, inputText: string, options?: { delay?: number }) => {
	await page.locator(selector).pressSequentially(inputText, options);
};

/** Press a key */
export const press = async (page: Page, key: string) => {
	await page.keyboard.press(key);
};

/** Wait for a selector to be visible */
export const waitForSelector = async (page: Page, selector: string) => {
	await page.locator(selector).waitFor({ state: 'visible' });
};

/** Get a locator */
export const locator = (page: Page, selector: string): Locator => {
	return page.locator(selector);
};

/** Count elements matching selector */
export const count = async (page: Page, selector: string, expected: number) => {
	await expect(page.locator(selector)).toHaveCount(expected);
};

/** Check if text exists on page */
export const text = async (page: Page, content: string) => {
	// For JSON-like content with special characters, search in page content directly
	// The text= selector has issues with quotes, braces, etc.
	await page.waitForFunction(
		(searchText) => {
			return document.body.innerText.includes(searchText) ||
				document.body.innerHTML.includes(searchText);
		},
		content,
		{ timeout: 10000 }
	);
};

/** Count occurrences of text in the editor */
export const countText = async (page: Page, value: string, expectedCount: number) => {
	const escapedValue = value.replace(/([.*+?^=!:${}()|[\]/\\])/g, '\\$1');
	const regexPattern = new RegExp(escapedValue, 'g');

	await page.locator('.is-root-container').evaluate(
		(container, { pattern, cnt }) => {
			return new Promise<void>((resolve, reject) => {
				const timeout = setTimeout(
					() => reject(new Error(`Expected ${cnt} matches for "${pattern}"`)),
					30000
				);
				const check = () => {
					const matches = container.innerHTML.match(new RegExp(pattern, 'g')) || [];
					if (matches.length === cnt) {
						clearTimeout(timeout);
						resolve();
					} else {
						requestAnimationFrame(check);
					}
				};
				check();
			});
		},
		{ pattern: escapedValue, cnt: expectedCount }
	);

	const html = await page.locator('.is-root-container').innerHTML();
	const matches = html.match(regexPattern) || [];
	expect(matches.length).toBe(expectedCount);
};

/** Check innerHTML of an element */
export const innerHTML = async (page: Page, selector: string, content: string, first = true) => {
	if (first) {
		await expect(page.locator(selector).first()).toHaveText(content);
	} else {
		await expect(page.locator(selector).last()).toHaveText(content);
	}
};

/** Check computed style of an element */
export const checkStyle = async (
	page: Page,
	selector: string,
	property: string,
	value: string,
	not = false
) => {
	await page.locator(selector).first().waitFor({ state: 'visible' });

	const computedValue = await page.locator(selector).first().evaluate(
		(el, prop) => getComputedStyle(el)[prop as any],
		property
	);

	if (not) {
		expect(computedValue).not.toBe(value);
	} else {
		expect(computedValue).toBe(value);
	}
};

/** Save the current post */
export const save = async (page: Page) => {
	// Wait a moment for any pending state changes
	await delay(500);

	// Check if already saved (Save button disabled or shows "Saved")
	const saveButton = page.locator('.editor-header__settings button:has-text("Save"):not(:has-text("Saved"))').first();
	const publishButton = page.locator('.editor-header__settings button:has-text("Publish")').first();
	const savedButton = page.locator('.editor-header__settings button:has-text("Saved")').first();

	// If "Saved" button is visible, nothing to do
	if (await savedButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		return;
	}

	// If Save button is visible, check if it's enabled
	if (await saveButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		const isDisabled = await saveButton.isDisabled().catch(() => true);
		if (!isDisabled) {
			await saveButton.click();
			// Wait for save to complete
			await page.waitForFunction(
				() => {
					const btn = document.querySelector('.editor-header__settings button');
					if (!btn) return false;
					const text = btn.textContent || '';
					return text.includes('Saved') || btn.hasAttribute('disabled');
				},
				{ timeout: 15000 }
			);
			return;
		}
		// Save button is disabled - nothing to save
		return;
	}

	// Publish button available (draft post)
	if (await publishButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		const isDisabled = await publishButton.isDisabled().catch(() => true);
		if (!isDisabled) {
			await publishButton.click();
			// Handle publish confirmation panel
			const confirmButton = page.locator('.editor-post-publish-panel .editor-post-publish-button');
			if (await confirmButton.isVisible({ timeout: 3000 }).catch(() => false)) {
				await confirmButton.click();
				// Wait for publish to complete
				await delay(2000);
			}
		}
	}
};

/** Save and reload the page (simple in wp-env - just reload!) */
export const saveAndReload = async (page: Page) => {
	await save(page);
	await delay(1000);
	await page.reload();
	await page.waitForSelector('.is-root-container', { timeout: 30000 });
};

/** Navigate to frontend */
export const navigateToFrontend = async (page: Page) => {
	// Wait for any publishing to complete
	const publishingButton = page.locator('button:has-text("Publishing")');
	if (await publishingButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		// Wait for publishing to finish
		await page.waitForSelector('button:has-text("Publishing")', { state: 'hidden', timeout: 30000 }).catch(() => {});
		await delay(1000);
	}

	// Close any publish panel that might be open
	const cancelButton = page.locator('.editor-post-publish-panel button:has-text("Cancel")').first();
	if (await cancelButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		await cancelButton.click();
		await delay(500);
	}

	// Try various ways to view the post
	const viewSelectors = [
		// Snackbar link after save/publish
		'.components-snackbar a:has-text("View Post")',
		'.components-snackbar a:has-text("View")',
		// Post-publish panel
		'.post-publish-panel__postpublish-buttons a',
		// Toolbar View button (opens in new tab or shows preview)
		'.editor-header__settings a[aria-label="View Post"]',
		// Generic view link
		'a[aria-label="View Post"]',
		'a:has-text("View Post")',
	];

	for (const selector of viewSelectors) {
		const link = page.locator(selector).first();
		if (await link.isVisible({ timeout: 2000 }).catch(() => false)) {
			await link.click();
			await delay(2000);
			return;
		}
	}

	// Last resort: Get the post permalink and navigate directly
	const postId = await page.evaluate(() => {
		const post = (window as any).wp?.data?.select('core/editor')?.getCurrentPostId();
		return post;
	});

	if (postId) {
		await page.goto(`http://localhost:8888/?p=${postId}`);
		await delay(2000);
		return;
	}

	throw new Error('No View Post link found. Make sure the post is saved first.');
};

/** Navigate back to editor from frontend */
export const navigateToEditor = async (page: Page) => {
	const editLink = page.locator('#wp-admin-bar-edit a').first();
	if (await editLink.isVisible({ timeout: 2000 }).catch(() => false)) {
		await editLink.click();
		await page.waitForSelector('.is-root-container', { timeout: 30000 });
	}
};

/** Open block inserter */
export const openBlockInserter = async (page: Page) => {
	const inserterButton = page.locator('button[aria-label="Toggle block inserter"]');
	const isPressed = await inserterButton.getAttribute('aria-pressed');
	if (isPressed !== 'true') {
		await inserterButton.click();
		await page.waitForSelector('.block-editor-inserter__menu', { timeout: 5000 });
	}
};

/** Add a block by name */
export const addBlock = async (page: Page, blockName: string) => {
	await openBlockInserter(page);
	await page.fill('.block-editor-inserter__search input', blockName);
	await delay(800);

	// Find all matching blocks
	const blocks = page.locator('.block-editor-block-types-list__item');
	const blockCount = await blocks.count();

	if (blockCount === 0) {
		throw new Error(`No blocks found for search "${blockName}"`);
	}

	// If only one result, click it
	if (blockCount === 1) {
		await blocks.first().click();
		await delay(500);
		return;
	}

	// Multiple results: collect all blocks with their text for smart selection
	const blockInfos: { index: number; text: string }[] = [];
	for (let i = 0; i < blockCount; i++) {
		const block = blocks.nth(i);
		const text = (await block.textContent()) || '';
		blockInfos.push({ index: i, text });
	}

	// Convert search term to expected block title (e.g., "type-code" -> "native code")
	const searchNormalized = blockName
		.replace(/^(type|component)-/, '')
		.replace(/-/g, ' ')
		.toLowerCase();

	// Priority 1: Exact match (excluding twig variants)
	// Look for block whose title matches exactly what we're searching for
	for (const info of blockInfos) {
		const textLower = info.text.toLowerCase().trim();
		const textNormalized = textLower.replace(/^native\s+/, '');

		// Skip twig blocks
		if (textLower.includes('twig')) continue;

		// Check for exact match
		if (textNormalized === searchNormalized) {
			await blocks.nth(info.index).click();
			await delay(500);
			return;
		}
	}

	// Priority 2: Shortest non-twig match (likely the base block, not a variant)
	const nonTwigBlocks = blockInfos.filter(
		(info) => !info.text.toLowerCase().includes('twig')
	);
	if (nonTwigBlocks.length > 0) {
		// Sort by text length (shorter = more likely to be the base block)
		nonTwigBlocks.sort((a, b) => a.text.length - b.text.length);
		await blocks.nth(nonTwigBlocks[0].index).click();
		await delay(500);
		return;
	}

	// Fallback: first block
	await blocks.first().click();
	await delay(500);
};

/** Remove all blocks */
export const removeBlocks = async (page: Page) => {
	await page.evaluate(() => {
		(window as any).wp.data.dispatch('core/block-editor').resetBlocks([]);
	});
};

/** Open sidebar */
export const openSidebar = async (page: Page) => {
	const sidebarButton = page.locator('button[aria-label="Settings"][aria-expanded="false"]');
	if (await sidebarButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		await sidebarButton.click();
	}

	// Make sure Block tab is selected
	const blockTab = page.locator('button[data-tab-id="edit-post/block"]');
	if (await blockTab.isVisible({ timeout: 1000 }).catch(() => false)) {
		const isSelected = await blockTab.getAttribute('aria-selected');
		if (isSelected !== 'true') {
			await blockTab.click();
		}
	}
};

/** Add a Tailwind class using the builder */
export const addTailwindClass = async (page: Page, selector: string, className: string) => {
	const listViewVisible = await page
		.locator('.editor-list-view-sidebar')
		.isVisible()
		.catch(() => false);

	if (!listViewVisible) {
		await page.locator('.editor-document-tools__document-overview-toggle').click();
	}
	await page.locator('.block-editor-list-view-block__contents-container').click();
	await page.locator('.blockstudio-builder__controls [role="combobox"]').click();
	await page.locator('.blockstudio-builder__controls [role="combobox"]').fill(className);
	await page.keyboard.press('ArrowDown');
	await page.keyboard.press('Enter');
};

/** Search for and add a block */
export const searchForBlock = async (page: Page, searchTerm: string, selector: string) => {
	await page.locator('[placeholder="Search"]').fill(searchTerm);
	await page.locator(selector).click();
};

/** Check for leftover attributes that should be removed */
export const checkForLeftoverAttributes = async (page: Page) => {
	const leftoverAttrs = [
		'useBlockProps',
		'tag',
		'allowedBlocks',
		'renderAppender',
		'template',
		'templateLock',
		'attribute',
		'placeholder',
		'allowedFormats',
		'autocompleters',
		'multiline',
		'preserveWhiteSpace',
		'withoutInteractiveFormatting',
	];

	for (const item of leftoverAttrs) {
		await count(page, `[${item.toLowerCase()}]`, 0);
	}
};

/** Click editor toolbar button */
export const clickEditorToolbar = async (page: Page, toolbarType: string, show: boolean) => {
	const toolbarSelector = `#blockstudio-editor-toolbar-${toolbarType}`;
	const elementSelector = `#blockstudio-editor-${toolbarType}`;

	const elementExists = await page.locator(elementSelector).isVisible().catch(() => false);

	if (!elementExists && show) {
		await page.locator(toolbarSelector).click();
		await delay(1000);
		await count(page, elementSelector, 1);
	}
	if (elementExists && !show) {
		await page.locator(toolbarSelector).click();
		await delay(1000);
		await count(page, elementSelector, 0);
	}
};

/**
 * Creates a standardized test suite for a field type.
 *
 * This is the main test generator used by all type tests.
 * It creates tests for:
 * - defaults (add block, check defaults, remove)
 * - outer fields (block at root level)
 * - inner fields (block inside InnerBlocks)
 */
export const testType = (
	field: string,
	defaultValue: any = false,
	testCallback: ((page: Page) => any[]) | false = false,
	removeAtEnd = true
) => {
	test.describe.configure({ mode: 'serial' });

	test.describe(field, () => {
		test.describe('defaults', () => {
			test('add block', async ({ editor }) => {
				await removeBlocks(editor);
				await addBlock(editor, `type-${field}`);
				await count(editor, '.is-root-container > .wp-block', 1);
			});

			test('has correct defaults', async ({ editor }) => {
				if (defaultValue) {
					await text(editor, `${defaultValue}`);
				}
			});

			test('remove block', async ({ editor }) => {
				await removeBlocks(editor);
			});
		});

		test.describe('fields', () => {
			for (const fieldType of ['outer', 'inner']) {
				test.describe(fieldType, () => {
					if (fieldType === 'outer') {
						test('add block', async ({ editor }) => {
							await removeBlocks(editor);
							await addBlock(editor, `type-${field}`);
							await count(editor, '.is-root-container > .wp-block', 1);
							await openSidebar(editor);
						});
					} else {
						test('add InnerBlocks', async ({ editor }) => {
							await removeBlocks(editor);
							await addBlock(editor, 'component-innerblocks-bare-spaceless');
							await count(editor, '.is-root-container > .wp-block', 1);

							// Click inside the InnerBlocks container to select it
							await editor.locator('[data-type="blockstudio/component-innerblocks-bare-spaceless"]').click();
							await delay(500);

							// Use the main inserter to add the test block inside
							await addBlock(editor, `type-${field}`);
							await delay(500);

							// Click on the inner block to select it
							await editor.locator(`[data-type="blockstudio/type-${field}"]`).click();
							await delay(500);
							await openSidebar(editor);
						});
					}

					if (testCallback && typeof testCallback === 'function') {
						// Skip repeater inside InnerBlocks
						if (fieldType === 'inner' && field === 'repeater') {
							return;
						}

						const testItems = testCallback({} as Page);

						if (Array.isArray(testItems)) {
							for (const testItem of testItems) {
								if (testItem.description && typeof testItem.testFunction === 'function') {
									test(testItem.description, async ({ editor }) => {
										await testItem.testFunction(editor);
									});
								}
							}
						}
					}
				});
			}

			if (removeAtEnd) {
				test('remove block', async ({ editor }) => {
					await removeBlocks(editor);
				});
			}
		});
	});
};

/** Creates a test suite for context/editor tests */
export const testContext = (
	contextType: string,
	testCallback: ((page: Page) => any[]) | false = false
) => {
	test.describe(contextType, () => {
		test.describe('open structure', () => {
			test('open first level', async ({ editor }) => {
				await editor.locator('#instance-plugins-blockstudio-includes-library').click();
				await count(editor, '#folder-plugins-blockstudio-includes-library-blockstudio-element', 1);
			});

			test('open second level', async ({ editor }) => {
				await editor.locator('#folder-plugins-blockstudio-includes-library-blockstudio-element').click();
				await count(editor, '#folder-plugins-blockstudio-includes-library-blockstudio-element-code', 1);
			});

			test('open third level', async ({ editor }) => {
				await editor.locator('#folder-plugins-blockstudio-includes-library-blockstudio-element-code').click();
				await count(editor, '#file-plugins-blockstudio-includes-library-blockstudio-element-code-block-json', 1);
			});
		});

		if (testCallback && typeof testCallback === 'function') {
			const testItems = testCallback({} as Page);
			if (Array.isArray(testItems)) {
				for (const testItem of testItems) {
					if (testItem.description && typeof testItem.testFunction === 'function') {
						test(testItem.description, async ({ editor }) => {
							await testItem.testFunction(editor);
						});
					}
				}
			}
		}
	});
};
