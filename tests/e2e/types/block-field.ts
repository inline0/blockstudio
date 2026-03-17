import { test, expect, Page } from '@playwright/test';
import { login } from '../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Block Field', () => {
  test('renders referenced block inside host block', async () => {
    await page.goto('http://localhost:8888/block-field-test/', {
      waitUntil: 'domcontentloaded',
    });

    const host = page.locator('.bs-block-field').first();
    await expect(host).toBeVisible();
    await expect(host.locator('.bf-label')).toHaveText('Field Host');
  });

  test('renders component block output in card slot', async () => {
    const card = page
      .locator('.bs-block-field')
      .first()
      .locator('.bf-card .bs-component');
    await expect(card).toBeVisible();
    await expect(card.locator('.comp-heading')).toHaveText('Embedded Card');
    await expect(card.locator('.comp-content')).toHaveText(
      'From block field'
    );
  });

  test('component CSS loads for embedded block', async () => {
    const styleTag = page.locator(
      'link[id*="type-component"][rel="stylesheet"]'
    );
    await expect(styleTag.first()).toBeAttached();
  });

  test('override renders alternative block in card slot', async () => {
    const overrideHost = page.locator('.bs-block-field').nth(1);
    await expect(overrideHost).toBeVisible();
    await expect(overrideHost.locator('.bf-label')).toHaveText(
      'Override Host'
    );

    const altCard = overrideHost.locator('.bf-card .bs-component-alt');
    await expect(altCard).toBeVisible();
    await expect(altCard.locator('.comp-alt-heading')).toHaveText(
      'Override Card'
    );
    await expect(altCard.locator('.comp-alt-content')).toHaveText(
      'From override'
    );
  });

  test('override does not render default block', async () => {
    const overrideHost = page.locator('.bs-block-field').nth(1);
    const defaultCard = overrideHost.locator('.bf-card .bs-component');
    await expect(defaultCard).toHaveCount(0);
  });
});
