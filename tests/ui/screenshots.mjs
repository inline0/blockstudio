import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8879';
const DIR = './screenshots';

async function shot(page, name, fn, fullPage = true) {
	try {
		if (fn) await fn(page);
		await page.screenshot({ path: `${DIR}/${name}.png`, fullPage });
		console.log(`  ${name}.png`);
	} catch (e) {
		console.log(`  SKIP ${name}: ${e.message.split('\n')[0]}`);
	}
}

async function main() {
	const browser = await chromium.launch();
	const page = await browser.newPage({ viewport: { width: 800, height: 600 } });

	console.log('Capturing screenshots...\n');

	// Showcase
	await page.goto(`${BASE}/component-showcase/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'showcase-full');

	// Tabs
	await page.goto(`${BASE}/tabs-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'tabs-default');
	await shot(page, 'tabs-switched', async (p) => {
		await p.locator('[role="tab"]').nth(1).click();
	});

	// Accordion
	await page.goto(`${BASE}/accordion-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'accordion-default');
	await shot(page, 'accordion-toggled', async (p) => {
		await p.locator('[data-bsui-accordion-trigger]').nth(1).click();
	});

	// Collapsible
	await page.goto(`${BASE}/collapsible-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'collapsible-closed');
	await shot(page, 'collapsible-open', async (p) => {
		await p.locator('[data-bsui-collapsible-root] button').first().click();
	});

	// Toggle
	await page.goto(`${BASE}/toggle-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'toggle');

	// Switch
	await page.goto(`${BASE}/switch-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'switch');

	// Checkbox
	await page.goto(`${BASE}/checkbox-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'checkbox');

	// Radio
	await page.goto(`${BASE}/radio-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'radio');

	// Select
	await page.goto(`${BASE}/select-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'select-closed');
	await shot(page, 'select-open', async (p) => {
		await p.locator('[data-bsui-select-trigger]').click();
		await p.waitForTimeout(200);
	});

	// Menu
	await page.goto(`${BASE}/menu-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'menu-closed');
	await shot(page, 'menu-open', async (p) => {
		await p.locator('[data-bsui-menu-trigger]').click();
		await p.waitForTimeout(200);
	});

	// Tooltip
	await page.goto(`${BASE}/tooltip-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'tooltip-hidden');
	await shot(page, 'tooltip-visible', async (p) => {
		const trigger = p.locator('[data-bsui-tooltip-root]').nth(1).locator('button');
		await trigger.hover();
		await p.waitForTimeout(200);
	});

	// Popover
	await page.goto(`${BASE}/popover-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'popover-closed');
	await shot(page, 'popover-open', async (p) => {
		await p.locator('[data-bsui-popover-root] [aria-haspopup="dialog"]').click();
		await p.waitForTimeout(200);
	});

	// Dialog
	await page.goto(`${BASE}/dialog-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'dialog-closed');
	await shot(page, 'dialog-open', async (p) => {
		await p.locator('[data-bsui-dialog-trigger]').click();
		await p.waitForTimeout(200);
	}, false);

	// Slider
	await page.goto(`${BASE}/slider-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'slider');

	// Progress
	await page.goto(`${BASE}/progress-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'progress');

	// Toggle Group
	await page.goto(`${BASE}/toggle-group-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'toggle-group');

	// Number Field
	await page.goto(`${BASE}/number-field-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'number-field');

	// Alert Dialog
	await page.goto(`${BASE}/alert-dialog-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'alert-dialog-open', async (p) => {
		await p.locator('[data-bsui-alert-dialog-trigger]').click();
		await p.waitForTimeout(200);
	}, false);

	// Drawer
	await page.goto(`${BASE}/drawer-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'drawer-open', async (p) => {
		await p.locator('[data-bsui-drawer-trigger]').click();
		await p.waitForTimeout(200);
	}, false);

	// Toolbar
	await page.goto(`${BASE}/toolbar-test/`);
	await page.waitForLoadState('networkidle');
	await shot(page, 'toolbar');

	await browser.close();
	console.log('\nDone!');
}

main();
