import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots', 'components');

test.beforeAll(async () => {
	fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
});

test.beforeEach(async ({ page }) => {
	await page.goto('/kitchen-sink/');
	await page.waitForLoadState('networkidle');
});

// Control height consistency
test.describe('Control heights', () => {
	const EXPECTED_HEIGHT = 36;

	const controls: Record<string, string> = {
		'toggle-group-item': '[data-bsui-toggle-group-item]',
		'toolbar-button': '[data-bsui-toolbar-button]',
		'standalone-toggle': '[data-bsui-toggle] [aria-pressed]',
		'select-trigger': ':not([role="toolbar"]) > [data-bsui-select-root] [data-bsui-select-trigger]',
		'input': 'input[type="email"]',
		'menu-trigger': '[data-bsui-menu-root]:not([data-bsui-breadcrumb] *) [data-bsui-menu-trigger] [data-bsui-button]',
		'number-field-button': '[role="group"]:has([role="spinbutton"]) > button',
		'number-field-input': '[role="spinbutton"]',
		'toolbar-select': '[role="toolbar"] [data-bsui-select-trigger]',
	};

	for (const [name, selector] of Object.entries(controls)) {
		test(`${name} is ${EXPECTED_HEIGHT}px tall`, async ({ page }) => {
			const el = page.locator(selector).first();
			const box = await el.boundingBox();
			expect(box).not.toBeNull();
			expect(box!.height).toBe(EXPECTED_HEIGHT);
		});
	}
});

// Individual component screenshots
test.describe('Component screenshots', () => {
	const components: Record<string, { selector: string; name: string }> = {
		'tabs': { selector: '[role="tablist"]', name: 'tabs' },
		'toggle-group': { selector: '[data-bsui-toggle-group-root]:not([role="toolbar"] *)', name: 'toggle-group' },
		'toolbar': { selector: '[role="toolbar"]', name: 'toolbar' },
		'toggle': { selector: '[data-bsui-toggle] [aria-pressed]', name: 'toggle' },
		'select': { selector: ':not([role="toolbar"]) > [data-bsui-select-root]', name: 'select' },
		'input': { selector: 'input[type="email"]', name: 'input' },
		'menu': { selector: '[data-bsui-menu-root]:not([data-bsui-breadcrumb] *)', name: 'menu' },
		'switch': { selector: '[role="switch"]', name: 'switch' },
		'checkbox': { selector: '[role="checkbox"]', name: 'checkbox' },
		'radio-group': { selector: '[role="radiogroup"]', name: 'radio-group' },
		'slider': { selector: '[role="slider"]', name: 'slider' },
		'progress': { selector: '[role="progressbar"]', name: 'progress' },
		'number-field': { selector: '[role="group"]:has([role="spinbutton"])', name: 'number-field' },
		'accordion': { selector: '[data-bsui-accordion-root]', name: 'accordion' },
		'aspect-ratio': { selector: '[data-bsui-aspect-ratio]', name: 'aspect-ratio' },
		'badge': { selector: '[data-bsui-badge]', name: 'badge' },
		'breadcrumb': { selector: '[data-bsui-breadcrumb]', name: 'breadcrumb' },
		'button': { selector: '[data-bsui-button]', name: 'button' },
		'card': { selector: '[data-bsui-card]', name: 'card' },
		'calendar': { selector: '[data-bsui-calendar]', name: 'calendar' },
		'menubar': { selector: '[data-bsui-menubar]', name: 'menubar' },
		'navigation-menu': { selector: '[data-bsui-nav-menu]', name: 'navigation-menu' },
		'carousel': { selector: '[data-bsui-carousel]', name: 'carousel' },
		'combobox': { selector: '[data-bsui-combobox-root]', name: 'combobox' },
		'pagination': { selector: '[data-bsui-pagination]', name: 'pagination' },
		'kbd': { selector: '[data-bsui-kbd]', name: 'kbd' },
		'skeleton': { selector: '[data-bsui-skeleton]', name: 'skeleton' },
		'spinner': { selector: '[data-bsui-spinner][data-size="lg"]', name: 'spinner' },
		'textarea': { selector: '[data-bsui-textarea]', name: 'textarea' },
	};

	for (const [, { selector, name }] of Object.entries(components)) {
		test(`capture ${name}`, async ({ page }) => {
			const el = page.locator(selector).first();
			await expect(el).toBeVisible();
			await el.screenshot({ path: path.join(SCREENSHOT_DIR, `${name}.png`), animations: 'disabled' });
		});
	}
});

test.describe('Layered styles', () => {
	test('button CSS emits in the bsui layer', async ({ page }) => {
		const styleText = await page.locator('style').evaluateAll((styles) =>
			styles.map((style) => style.textContent || '').join('\n')
		);

		expect(styleText).toContain('@layer bsui');
		expect(styleText).toContain('[data-bsui-button]');
	});

	test('unlayered theme utility overrides bsui button styles', async ({ page }) => {
		await page.addStyleTag({
			content: '.bsui-layer-override { background-color: rgb(255, 0, 0); }',
		});

		const button = page.locator('[data-bsui-button]').first();
		await expect(button).toBeVisible();
		await button.evaluate((element) => element.classList.add('bsui-layer-override'));

		await expect(button).toHaveCSS('background-color', 'rgb(255, 0, 0)');
	});

	test('button icon renders in the requested position', async ({ page }) => {
		const button = page.locator('[data-bsui-button]').filter({ hasText: 'Icon right' }).first();
		await expect(button).toBeVisible();

		const children = await button.evaluate((element) =>
			Array.from(element.children).map((child) =>
				child.hasAttribute('data-bsui-button-icon') ? 'icon' : child.textContent?.trim()
			)
		);

		expect(children).toEqual(['Icon right', 'icon']);
		await expect(button.locator('[data-bsui-button-icon]')).toHaveCSS('width', '16px');
		await expect(button.locator('[data-bsui-button-icon] svg')).toHaveCSS('width', '16px');
	});
});
