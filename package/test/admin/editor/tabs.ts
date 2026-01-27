import { test, expect, Page } from '@playwright/test';
import { count, pEditor, searchForBlock } from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
  await searchForBlock(
    page,
    'native',
    '#block-plugins-blockstudio-package-test-blocks-single-native-index-php'
  );
  await page.locator('.mtk10').first().waitFor({ state: 'visible' });
});

test.afterAll(async () => {
  await page.close();
});

test.describe('tabs', () => {
  test.describe('should navigate to', () => {
    test('block.json', async () => {
      await expect(page.locator('.mtk8').first()).toHaveText('div');
    });

    test('script-editor.js', async () => {
      await page.locator('#blockstudio-editor-tab-script-editor-js').click();
      await expect(page.locator('.mtk1').first()).toHaveText('setInterval');
      await page.keyboard.type('console.log("test");');
    });

    test('script-inline.js', async () => {
      await page.locator('#blockstudio-editor-tab-script-inline-js').click();
      await expect(page.locator('.mtk1').first()).toHaveText('setTimeout');
    });

    test('script-view.js', async () => {
      await page.locator('#blockstudio-editor-tab-script-view-js').click();
      await expect(page.locator('.mtk1').first()).toHaveText('setTimeout');
    });

    test('script.js', async () => {
      await page.locator('#blockstudio-editor-tab-script-js').click();
      await expect(page.locator('.mtk1').first()).toHaveText('setTimeout');
    });

    test('style-editor.css', async () => {
      await page.locator('#blockstudio-editor-tab-style-editor-css').click();
      await expect(page.locator('.mtk8').first()).toHaveText('.test');
    });

    test('style-inline.css', async () => {
      await page.locator('#blockstudio-editor-tab-style-inline-css').click();
      await expect(page.locator('.mtk8').first()).toHaveText('body:not');
    });

    test('style-scoped.css', async () => {
      await page.locator('#blockstudio-editor-tab-style-scoped-css').click();
      await expect(page.locator('.mtk8').first()).toHaveText('h2');
    });

    test('style.css', async () => {
      await page.locator('#blockstudio-editor-tab-style-css').click();
      await expect(page.locator('.mtk8').first()).toHaveText('.tester-default');
    });

    test('add another file', async () => {
      await page.click('[aria-label="Add file"]');
      await page.fill('.components-text-control__input', 'a.css');
      await page.click('text=Create file');
      await count(page, '#blockstudio-editor-tab-a-css', 1);
      await page.click('#blockstudio-editor-toolbar-more');
      await page.click('text=Delete file');
      await page.click(
        '.components-modal__content .components-button.is-destructive'
      );
      await count(page, '#blockstudio-editor-tab-a-css', 0);
      await expect(page.locator('.mtk9').first()).toHaveText('{');
    });
  });
});
