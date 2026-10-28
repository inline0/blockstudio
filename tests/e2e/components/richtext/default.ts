import { Frame, Page, test } from '@playwright/test';
import {
  addBlock,
  checkForLeftoverAttributes,
  count,
  getEditorCanvas,
  getSharedPage,
  resetPageState,
  save,
} from '../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
  canvas = await getEditorCanvas(page);
});

test.describe('component-richtext-default', () => {
  test('add block', async () => {
    await addBlock(page, 'component-richtext-default');
    await count(canvas, '.is-root-container > .wp-block', 1);
  });

  test.describe('editor', () => {
    test('placeholder', async () => {
      await count(canvas, '[aria-label="Enter text here"]', 3);
    });

    test('classes', async () => {
      await count(canvas, '.blockstudio-test__block.test.test2.test3', 3);
    });

    test('format controls respect camelCase props', async () => {
      const firstRichText = canvas
        .locator('[aria-label="Enter text here"]')
        .nth(0);
      const secondRichText = canvas
        .locator('[aria-label="Enter text here"]')
        .nth(1);
      const thirdRichText = canvas
        .locator('[aria-label="Enter text here"]')
        .nth(2);
      const formatButton = (label: string) =>
        `.block-editor-block-toolbar [aria-label="${label}"]`;
      const selectAll = async () => page.keyboard.press('ControlOrMeta+A');

      await firstRichText.click();
      await page.keyboard.type('Plain format');
      await selectAll();
      await count(page, formatButton('Bold'), 0);
      await count(page, formatButton('Link'), 0);
      await page.keyboard.press('ControlOrMeta+B');
      await count(canvas, 'h1 strong:has-text("Plain format")', 0);
      await selectAll();
      await page.keyboard.press('Backspace');

      await secondRichText.click();
      await page.keyboard.type('Bold format');
      await selectAll();
      await count(page, formatButton('Bold'), 1);
      await count(page, formatButton('Italic'), 0);
      await count(page, formatButton('Link'), 0);
      await page.keyboard.press('ControlOrMeta+B');
      await count(canvas, 'h2 strong:has-text("Bold format")', 1);
      await selectAll();
      await page.keyboard.press('Backspace');

      await thirdRichText.click();
      await page.keyboard.type('Link format');
      await selectAll();
      await count(page, formatButton('Link'), 1);
      await page.locator(formatButton('Link')).click();
      await count(page, '.block-editor-link-control', 1);
      await page.keyboard.press('Escape');
      await thirdRichText.click();
      await selectAll();
      await page.keyboard.press('Backspace');
      await count(canvas, '[aria-label="Enter text here"]', 3);
    });

    test('content', async () => {
      await canvas.locator('[aria-label="Enter text here"]').nth(0).click();
      await page.keyboard.type('Test text');
      await canvas.locator('[aria-label="Enter text here"]').nth(1).click();
      await page.keyboard.type('Test text');
      await count(canvas, 'text=Test text', 2);
    });
  });

  test('check content', async () => {
    await save(page);
    await page.goto('http://localhost:8888/native-single/');
    await checkForLeftoverAttributes(page);
    await count(page, 'text=Test text', 2);
    await count(page, 'h1.blockstudio-test__block.test.test2.test3', 1);
  });

  test('check content in editor', async () => {
    await page.goto(
      'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
    );
    canvas = await getEditorCanvas(page);
    await canvas.click('[data-type="blockstudio/component-richtext-default"]');
    await count(canvas, 'text=Test text', 2);
  });

  test('dollar signs preserved in content', async () => {
    await page.goto(
      'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
    );
    canvas = await getEditorCanvas(page);
    await canvas.click('[data-type="blockstudio/component-richtext-default"]');
    await canvas.locator('[aria-label="Enter text here"]').nth(0).click();
    await page.keyboard.press('Control+a');
    await page.keyboard.type('Price: $79');
    await save(page);
    await page.goto('http://localhost:8888/native-single/');
    await count(page, 'text=$79', 1);
  });
});
