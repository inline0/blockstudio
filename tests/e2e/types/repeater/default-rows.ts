import { test, Page } from '@playwright/test';
import {
  getSharedPage,
  getEditorCanvas,
  resetPageState,
  addBlock,
  openSidebar,
  save,
  text,
  count,
} from '../../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
});

test.describe('repeater default rows', () => {
  test.describe('default array', () => {
    test('insert block', async () => {
      await addBlock(page, 'type-repeater-default-rows');
      const canvas = await getEditorCanvas(page);
      await count(canvas, '.is-root-container > .wp-block', 1);
    });

    test('pre-filled rows appear in editor', async () => {
      const canvas = await getEditorCanvas(page);
      await text(canvas, '"title":"First Item"');
      await text(canvas, '"title":"Second Item"');
      await text(canvas, '"count":5');
      await text(canvas, '"count":10');
      await text(canvas, '"active":true');
      await text(canvas, '"active":false');
    });

    test('correct number of repeater rows in sidebar', async () => {
      await openSidebar(page);
      await count(page, '[data-rfd-draggable-id^="items["]', 2);
    });

    test('save and verify frontend output', async () => {
      await save(page);
      await page.goto('http://localhost:8888/?p=1483');
      await page.waitForLoadState('networkidle');
      await text(page, '"title":"First Item"');
      await text(page, '"title":"Second Item"');
      await text(page, '"count":5');
      await text(page, '"count":10');
      await text(page, '"active":true');
      await text(page, '"active":false');
    });

    test('clean up', async () => {
      await resetPageState(page);
    });
  });

  test.describe('min rows', () => {
    test('insert block', async () => {
      await addBlock(page, 'type-repeater-min-rows');
      const canvas = await getEditorCanvas(page);
      await count(canvas, '.is-root-container > .wp-block', 1);
    });

    test('auto-generated rows with field defaults in editor', async () => {
      const canvas = await getEditorCanvas(page);
      await text(canvas, '"name":"Default Name"');
      await text(canvas, '"value":42');
      await text(canvas, '"enabled":true');
    });

    test('correct number of repeater rows in sidebar', async () => {
      await openSidebar(page);
      await count(page, '[data-rfd-draggable-id^="entries["]', 3);
    });

    test('save and verify frontend output', async () => {
      await save(page);
      await page.goto('http://localhost:8888/?p=1483');
      await page.waitForLoadState('networkidle');
      await text(page, '"name":"Default Name"');
      await text(page, '"value":42');
      await text(page, '"enabled":true');
    });

    test('clean up', async () => {
      await resetPageState(page);
    });
  });
});
