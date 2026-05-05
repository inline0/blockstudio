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
