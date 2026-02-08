import { test, expect, Page, Frame } from '@playwright/test';
import {
  login,
  getEditorCanvas,
  addBlock,
  save,
} from './utils/playwright-utils';

let page: Page;
let canvas: Frame;
const POST_URL = 'http://localhost:8888/wp-admin/post.php?post=1483&action=edit';
const frontendUrl = 'http://localhost:8888/?p=1483&blockstudio-devtools';
const frontendUrlNoDevtools = 'http://localhost:8888/?p=1483';

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  page.on('dialog', async (dialog) => {
    await dialog.accept();
  });

  await login(page);

  await page.goto(POST_URL);
  canvas = await getEditorCanvas(page);
  await canvas.waitForSelector('.is-root-container', { timeout: 10000 });

  const modal = await page.$('text=Welcome to the block editor');
  if (modal) {
    await page.click(
      '.components-modal__screen-overlay .components-button.has-icon',
    );
  }

  await page.keyboard.press('Escape');
  await page.evaluate(() => {
    (window as any).wp.data.dispatch('core/block-editor').resetBlocks([]);
  });

  await addBlock(page, 'component-useblockprops-default');
  canvas = await getEditorCanvas(page);
  await expect(canvas.locator('.is-root-container > .wp-block')).toHaveCount(1);
  await save(page);
});

test.afterAll(async () => {
  await page.goto(POST_URL);
  canvas = await getEditorCanvas(page);
  await canvas.waitForSelector('.is-root-container', { timeout: 10000 });
  await page.keyboard.press('Escape');
  await page.evaluate(() => {
    (window as any).wp.data.dispatch('core/block-editor').resetBlocks([]);
  });
  await save(page);
  await page.close();
});

test.describe('Devtools', () => {
  test.describe('query param gating', () => {
    test('data-blockstudio-path present with ?blockstudio-devtools', async () => {
      await page.goto(frontendUrl);
      await page.waitForLoadState('networkidle');

      const paths = await page.locator('[data-blockstudio-path]').all();
      expect(paths.length).toBeGreaterThan(0);

      const firstPath = await paths[0].getAttribute('data-blockstudio-path');
      expect(firstPath).toBeTruthy();
      expect(firstPath).toMatch(/\.php$|\.twig$/);
    });

    test('data-blockstudio-path NOT present without query param', async () => {
      await page.goto(frontendUrlNoDevtools);
      await page.waitForLoadState('networkidle');

      const paths = await page.locator('[data-blockstudio-path]').all();
      expect(paths.length).toBe(0);
    });

    test('devtools script enqueued with ?blockstudio-devtools', async () => {
      await page.goto(frontendUrl);
      await page.waitForLoadState('networkidle');

      const script = page.locator('script[id="blockstudio-devtools-js"]');
      await expect(script).toHaveCount(1);
    });

    test('devtools script NOT enqueued without query param', async () => {
      await page.goto(frontendUrlNoDevtools);
      await page.waitForLoadState('networkidle');

      const script = page.locator('script[id="blockstudio-devtools-js"]');
      await expect(script).toHaveCount(0);
    });
  });

  test.describe('logged-out users', () => {
    test('no data-blockstudio-path even with query param', async ({
      browser,
    }) => {
      const anonContext = await browser.newContext();
      const anonPage = await anonContext.newPage();

      await anonPage.goto(frontendUrl);
      await anonPage.waitForLoadState('networkidle');

      const paths = await anonPage.locator('[data-blockstudio-path]').all();
      expect(paths.length).toBe(0);

      await anonPage.close();
      await anonContext.close();
    });

    test('no devtools script even with query param', async ({ browser }) => {
      const anonContext = await browser.newContext();
      const anonPage = await anonContext.newPage();

      await anonPage.goto(frontendUrl);
      await anonPage.waitForLoadState('networkidle');

      const script = anonPage.locator('script[id="blockstudio-devtools-js"]');
      await expect(script).toHaveCount(0);

      await anonPage.close();
      await anonContext.close();
    });
  });

  test.describe('grabber interaction', () => {
    test('activates on Cmd+C hold', async () => {
      await page.goto(frontendUrl);
      await page.waitForLoadState('networkidle');

      await page.keyboard.down('Meta');
      await page.keyboard.down('c');
      await page.waitForTimeout(200);

      const overlay = page.locator('[data-blockstudio-devtools="overlay"]');
      await expect(overlay).toBeVisible();

      // Deactivate with Escape
      await page.keyboard.up('c');
      await page.keyboard.up('Meta');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(100);
    });

    test('does not activate on quick Cmd+C', async () => {
      await page.goto(frontendUrl);
      await page.waitForLoadState('networkidle');

      await page.keyboard.down('Meta');
      await page.keyboard.down('c');
      await page.waitForTimeout(30);
      await page.keyboard.up('c');
      await page.keyboard.up('Meta');

      await page.waitForTimeout(100);

      const overlay = page.locator(
        '[data-blockstudio-devtools="overlay"]:visible',
      );
      await expect(overlay).toHaveCount(0);
    });

    test('highlights element and shows label on hover', async () => {
      await page.goto(frontendUrl);
      await page.waitForLoadState('networkidle');

      const block = page.locator('[data-blockstudio-path]').first();
      await block.waitFor({ state: 'visible', timeout: 5000 });
      const box = await block.boundingBox();
      if (!box) throw new Error('Block not found on page');

      await page.keyboard.down('Meta');
      await page.keyboard.down('c');
      await page.waitForTimeout(200);

      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      await page.waitForTimeout(200);

      const label = page.locator('[data-blockstudio-devtools="label"]');
      await expect(label).toBeVisible();

      const labelText = await label.textContent();
      expect(labelText).toBeTruthy();

      // Deactivate
      await page.keyboard.up('c');
      await page.keyboard.up('Meta');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(100);
    });

    test('copies path to clipboard on click', async () => {
      const context = await page.context();
      await context.grantPermissions(['clipboard-read', 'clipboard-write']);

      await page.goto(frontendUrl);
      await page.waitForLoadState('networkidle');

      const block = page.locator('[data-blockstudio-path]').first();
      await block.waitFor({ state: 'visible', timeout: 5000 });
      const expectedPath = await block.getAttribute('data-blockstudio-path');
      const box = await block.boundingBox();
      if (!box) throw new Error('Block not found on page');

      // Activate
      await page.keyboard.down('Meta');
      await page.keyboard.down('c');
      await page.waitForTimeout(200);
      await page.keyboard.up('c');
      await page.keyboard.up('Meta');

      // Hover over block
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      await page.waitForTimeout(200);

      // Click to copy
      await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
      await page.waitForTimeout(200);

      const clipboard = await page.evaluate(() =>
        navigator.clipboard.readText(),
      );
      expect(clipboard).toContain('@<');
      expect(clipboard).toContain('in ');
      const filename = expectedPath!.split('/').pop()!;
      expect(clipboard).toContain(filename);
    });
  });
});
