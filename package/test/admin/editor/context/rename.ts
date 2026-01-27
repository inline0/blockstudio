import { Page, test } from '@playwright/test';
import {
  clickEditorToolbar,
  count,
  delay,
  testContext,
} from '../../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

testContext('rename', () => [
  {
    description: 'folder name already exists',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-code'
      );
      await page.keyboard.up('Control');
      await page.click('text=Rename');
      await delay(1000);
      await page.keyboard.type('slider');
      await count(page, 'text=Folder name already exists', 1);
    },
  },
  {
    description: 'code to test-folder',
    testFunction: async (page: Page) => {
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await delay(1000);
      await page.keyboard.type('test-folder');
      await page.click('.components-button.is-primary');
      await count(
        page,
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder',
        1
      );
    },
  },
  {
    description: 'test-folder to code',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder'
      );
      await page.keyboard.up('Control');
      await page.click('text=Rename');
      await delay(1000);
      await page.keyboard.type('code');
      await page.click('.components-button.is-primary');
      await count(
        page,
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-code',
        1
      );
    },
  },
  {
    description: 'file name already exists',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-code-script-inline-js'
      );
      await page.keyboard.up('Control');
      await page.click('text=Rename');
      await delay(1000);
      await page.keyboard.type('style-inline.scss');
      await count(page, 'text=File name already exists', 1);
    },
  },
  {
    description: 'script-inline.js to script.js',
    testFunction: async (page: Page) => {
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await delay(1000);
      await page.keyboard.type('script.js');
      await page.click('.components-button.is-primary');
      await count(
        page,
        '#file-plugins-blockstudio-includes-library-blockstudio-element-code-script-js',
        1
      );
    },
  },
  {
    description: 'script.js to script-inline.js',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-code-script-js'
      );
      await page.keyboard.up('Control');
      await page.click('text=Rename');
      await delay(1000);
      await page.keyboard.type('script-inline.js');
      await page.click('.components-button.is-primary');
      await count(
        page,
        '#file-plugins-blockstudio-includes-library-blockstudio-element-code-script-inline-js',
        1
      );
    },
  },
  {
    description: 'script-inline.js to script.js in editor',
    testFunction: async (page: Page) => {
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-code-index-php'
      );
      await clickEditorToolbar(page, 'files', true);
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-code-script-inline-js'
      );
      await page.keyboard.up('Control');
      await page.click('.components-button:has-text("Rename")');
      await delay(1000);
      await page.keyboard.type('script.js');
      await page.click('.components-button:has-text("Rename")');
      await count(
        page,
        '#file-plugins-blockstudio-includes-library-blockstudio-element-code-script-js',
        1
      );
      await count(page, '#blockstudio-editor-tab-script-js', 1);
    },
  },
  {
    description: 'script.js to script-inline.js in editor',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-code-script-js'
      );
      await page.keyboard.up('Control');
      await page.click('.components-button:has-text("Rename")');
      await delay(1000);
      await page.keyboard.type('script-inline.js');
      await page.click('.components-button:has-text("Rename")');
      await count(
        page,
        '#file-plugins-blockstudio-includes-library-blockstudio-element-code-script-inline-js',
        1
      );
      await count(page, '#blockstudio-editor-tab-script-inline-js', 1);
      await clickEditorToolbar(page, 'files', false);
    },
  },
]);
