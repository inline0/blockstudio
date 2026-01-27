import { expect, Page, test } from '@playwright/test';
import {
  clickEditorToolbar,
  count,
  getFrame,
  pEditor,
  searchForBlock,
} from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);

  await searchForBlock(
    page,
    'editor',
    '#block-plugins-blockstudio-package-test-blocks-single-native-nested-script-editor-js'
  );
  await page.locator('.mtk1').first().waitFor({ state: 'visible' });
});

test.afterAll(async () => {
  await clickEditorToolbar(page, 'console', false);
  await clickEditorToolbar(page, 'files', false);
  await clickEditorToolbar(page, 'preview', false);
  await page.close();
});

test.describe('code', () => {
  test('format on save', async () => {
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await count(page, '.view-line', 9);
    await page.click('text=Save Block');
    await count(page, '.view-line', 4);
  });

  test('cut index.php and init.php, and switch between', async () => {
    await page.click('#blockstudio-editor-toolbar-exit');
    await searchForBlock(
      page,
      'init',
      '#block-plugins-blockstudio-package-test-blocks-functions-init-init-php'
    );
    await page.keyboard.press('Meta+A', {
      delay: 1000,
    });
    await page.keyboard.press('Backspace', {
      delay: 1000,
    });
    await page.click('#blockstudio-editor-tab-index-php');
    await page.keyboard.press('Meta+A', {
      delay: 1000,
    });
    await page.keyboard.press('Backspace', {
      delay: 1000,
    });
    await page.click('#blockstudio-editor-tab-init-php');
    // await count(page, '.mtk14.mtkb', 0);
    await page.click('#blockstudio-editor-tab-index-php');
    await count(page, '.mtk10', 0);
  });

  test('switch blocks and values are reset', async () => {
    await clickEditorToolbar(page, 'files', true);
    await page.fill('.components-text-control__input', 'events');
    await page.click(
      '#block-plugins-blockstudio-package-test-blocks-functions-events-index-twig'
    );
    await page.click("text=Don't save");
    await page.fill('.components-text-control__input', 'init');
    await page.click(
      '#block-plugins-blockstudio-package-test-blocks-functions-init-index-php'
    );
    await count(page, '.mtk10', 4);
    await page.click('#blockstudio-editor-tab-init-php');
    await count(page, '.mtk14.mtkb', 1);
  });

  test.describe('autocomplete', () => {
    test('php functions', async () => {
      await page.click(
        '#block-plugins-blockstudio-package-test-blocks-functions-init-index-php'
      );
      await page.keyboard.press('Meta+A');
      await page.keyboard.press('Backspace');
      await page.keyboard.type('<?php wp_', {
        delay: 50,
      });
      await count(page, '.monaco-list-rows .monaco-list-row', 13);
    });

    test('css classes', async () => {
      await page.keyboard.press('Meta+A');
      await page.keyboard.press('Backspace');
      await page.keyboard.type('<div class="has-', {
        delay: 50,
      });
      await count(page, '.monaco-list-rows .monaco-list-row', 5);
    });
  });

  test('create error for init.php', async () => {
    await page.click('#blockstudio-editor-tab-init-php');
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText('<?php blablabla ?>');
    await page.keyboard.press('A', {
      delay: 500,
    });
    await page.keyboard.press('A', {
      delay: 500,
    });
    await page.keyboard.press('A', {
      delay: 500,
    });
    await page.click('text=Save Block');
    await clickEditorToolbar(page, 'console', true);
    await count(page, 'text=There is an error in your init.php', 1);
  });

  test('template insertion', async () => {
    await page.fill('.components-text-control__input', 'text');
    await page.click(
      '#block-plugins-blockstudio-package-test-blocks-types-text-index-twig'
    );
    await page.click("text=Don't save");
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.type('@attributes:', {
      delay: 100,
    });
    await page.keyboard.press('Enter');
    await clickEditorToolbar(page, 'preview', true);
    const preview = await getFrame(page, 'blockstudio-preview');
    await expect(preview.locator('text=Default value')).toHaveCount(1);
  });
});
