import { test, expect, Page, Frame } from '@playwright/test';
import {
  addBlock,
  count,
  getEditorCanvas,
  getSharedPage,
  openSidebar,
  removeBlocks,
  resetPageState,
} from '../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
  canvas = await getEditorCanvas(page);
});

test.describe('custom field conditions with idStructure', () => {
  test('add block', async () => {
    await addBlock(page, 'type-fields-conditions');
    await count(canvas, '.is-root-container > .wp-block', 1);
  });

  test('title_font_enable toggle exists', async () => {
    await openSidebar(page);
    const toggle = page.locator('label:has-text("Modify font size")').first();
    await expect(toggle).toBeVisible();
  });

  test('title_font_min is hidden when toggle is off', async () => {
    const minField = page.locator('label:has-text("Min Size")').first();
    await expect(minField).toBeHidden();
  });

  test('title_font_min appears when toggle is on', async () => {
    const toggle = page.locator('label:has-text("Modify font size")').first();
    await toggle.click();
    const minField = page.locator('label:has-text("Min Size")').first();
    await expect(minField).toBeVisible();
  });

  test('subtitle font fields hidden when show_advanced is off', async () => {
    const subtitleToggle = page
      .locator('label:has-text("Modify font size")')
      .nth(1);
    await expect(subtitleToggle).toBeHidden();
  });

  test('saved block has correct attribute names', async () => {
    const attrs = await page.evaluate(() => {
      const blocks = (window as any).wp.data
        .select('core/block-editor')
        .getBlocks();
      const block = blocks.find(
        (b: any) =>
          b.name === 'blockstudio/type-fields-conditions'
      );
      return block ? Object.keys(block.attributes.blockstudio?.attributes || {}) : [];
    });

    expect(attrs).toContain('title_font_enable');
    expect(attrs).toContain('title_font_min');
    expect(attrs).toContain('title_font_max');
  });

  test('remove block', async () => {
    await removeBlocks(page);
  });
});
