import { test, expect, Page, Frame } from '@playwright/test';
import {
  addBlock,
  count,
  getEditorCanvas,
  login,
  openSidebar,
  removeBlocks,
} from '../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  page.on('dialog', async (dialog) => {
    await dialog.accept();
  });
  await login(page);
  await page.goto('http://localhost:8888/wp-admin/post-new.php', {
    waitUntil: 'domcontentloaded',
  });
  canvas = await getEditorCanvas(page);
  await canvas.waitForSelector('.is-root-container', { timeout: 30000 });
  await page.keyboard.press('Escape');
});

test.afterAll(async () => {
  await page.close();
});

test.describe('custom field conditions with idStructure', () => {
  test('add block', async () => {
    await addBlock(page, 'type-fields-conditions');
    await count(canvas, '.is-root-container > .wp-block', 1);
    await openSidebar(page);
  });

  test('toggle exists for title font', async () => {
    const toggles = page.locator('.blockstudio-fields__field--toggle');
    await expect(toggles.first()).toBeVisible();
  });

  test('min field hidden when toggle is off', async () => {
    const minField = page.locator(
      '.blockstudio-fields__field--number:has-text("Min Size")'
    );
    await expect(minField).toHaveCount(0);
  });

  test('expanded attribute keys are correct', async () => {
    const attrKeys = await page.evaluate(() => {
      const blocks = (window as any).wp.data
        .select('core/block-editor')
        .getBlocks();
      const block = blocks.find(
        (b: any) => b.name === 'blockstudio/type-fields-conditions'
      );
      return Object.keys(block?.attributes?.blockstudio?.attributes || {});
    });

    expect(attrKeys).toContain('title_font_enable');
    expect(attrKeys).toContain('title_font_min');
    expect(attrKeys).toContain('title_font_max');
    expect(attrKeys).toContain('subtitle_font_enable');
  });

  test('condition IDs are rewritten with idStructure prefix', async () => {
    const conditions = await page.evaluate(() => {
      const blocksNative = (window as any).blockstudioAdmin?.data
        ?.blocksNative;
      const blockDef = Object.values(blocksNative).find(
        (b: any) => b.name === 'blockstudio/type-fields-conditions'
      ) as any;
      const fields = blockDef?.blockstudio?.attributes || [];
      const minField = fields.find(
        (f: any) => f.id === 'title_font_min'
      );
      return minField?.conditions;
    });

    expect(conditions).toBeDefined();
    expect(conditions[0][0].id).toBe('title_font_enable');
    expect(conditions[0][0].operator).toBe('==');
    expect(conditions[0][0].value).toBe(true);
  });

  test('enabling toggle shows conditional min field', async () => {
    await page.evaluate(() => {
      const input = document.querySelector(
        '.blockstudio-fields__field--toggle input[type="checkbox"]'
      ) as HTMLInputElement;
      if (input) input.click();
    });

    const minField = page.locator(
      '.blockstudio-fields__field--number:has-text("Min Size")'
    );
    await expect(minField.first()).toBeVisible({ timeout: 5000 });
  });

  test('disabling toggle hides conditional min field again', async () => {
    await page.evaluate(() => {
      const input = document.querySelector(
        '.blockstudio-fields__field--toggle input[type="checkbox"]'
      ) as HTMLInputElement;
      if (input) input.click();
    });

    const minField = page.locator(
      '.blockstudio-fields__field--number:has-text("Min Size")'
    );
    await expect(minField).toHaveCount(0, { timeout: 5000 });
  });

  test('remove block', async () => {
    await removeBlocks(page);
  });
});
