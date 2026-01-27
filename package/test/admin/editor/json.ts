import { Page, test } from '@playwright/test';
import {
  clickEditorToolbar,
  count,
  pEditor,
  searchForBlock,
} from '../../../playwright-utils';

let page: Page;

const code = `{
    "$schema": "https://app.blockstudio.dev/schema",
    "apiVersion": 2,
    "version": "1.0.0",
    "textdomain": "blockstudio",
    "name": "blockstudio-element/code",
    "title": "Code",
    "category": "blockstudio-elements",
    "icon": "star-filled",
    "description": "Highlight text like in a code editor.",
    "keywords": [
        "blockstudio",
        "code"
    ],
    "example": {
        "attributes": {
            "language": {
                "value": "javascript"
            },
            "code": "function highlightMe() { console.log('hello'); }",
            "lineNumbers": true,
            "copyButton": "true"
        }
    },
    "blockstudio": true`;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
  await searchForBlock(
    page,
    'code',
    '#block-plugins-blockstudio-includes-library-blockstudio-element-code-block-json'
  );
  await page.locator('.mtk9').first().waitFor({ state: 'visible' });
  await clickEditorToolbar(page, 'files', true);
  await clickEditorToolbar(page, 'preview', true);
  await clickEditorToolbar(page, 'console', true);
});

test.afterAll(async () => {
  await clickEditorToolbar(page, 'files', false);
  await clickEditorToolbar(page, 'preview', false);
  await clickEditorToolbar(page, 'console', false);
  await page.close();
});

test.describe('block.json', () => {
  test.describe('preview', () => {
    test('add different content', async () => {
      await page.click('.monaco-scrollable-element');
      await page.keyboard.press('Meta+A');
      await page.keyboard.press('Backspace');
      await page.keyboard.insertText(code);
      await count(page, 'text=No attributes found', 1);
    });
  });

  test.describe('error', () => {
    test('add different content', async () => {
      await page.click('.monaco-scrollable-element');
      await page.keyboard.press('A');
      await page.keyboard.press('S');
      await page.keyboard.press('D');
      await count(page, 'text=There is an error in your block.json', 0);
    });
  });

  test.describe('defaults', () => {
    test('text', async () => {
      await page.fill('.components-text-control__input', 'text');
      await page.click(
        '#block-plugins-blockstudio-package-test-blocks-types-text-block-json'
      );
      await page.click("text=Don't save");
      await count(page, '[value="Default value"]', 1);
    });

    // test('checkbox populate', async () => {
    //   await page.fill('.components-text-control__input', 'checkbox');
    //   await page.click(
    //     '#block-plugins-blockstudio-package-test-blocks-types-checkbox-block-json'
    //   );
    //   await page.click("text=Don't save");
    //   await count(page, 'text=blockstudio-child', 1);
    //   await count(page, 'text=Nathan Baldwin', 1);
    // });
  });

  test('extensions', async () => {
    await page.fill('.components-text-control__input', 'all');
    await page.click(
      '#block-plugins-blockstudio-package-test-blocks-extensions-all-json'
    );
    if (await page.$('text=Save changes on current block?')) {
      await page.click("text=Don't save");
    }
    await count(page, 'text=Addons all', 1);
  });
});
