import { Page, test } from '@playwright/test';
import { count, delay, testContext } from '../../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

testContext('new folder', () => [
  {
    description: 'name exists',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element'
      );
      await page.keyboard.up('Control');
      await page.click('text=New folder');
      await delay(1000);
      await page.keyboard.type('code');
      await count(page, 'text=Folder name already exists', 1);
    },
  },
  {
    description: 'create',
    testFunction: async (page: Page) => {
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await page.keyboard.press('Backspace');
      await delay(1000);
      await page.keyboard.type('test-folder');
      await page.click('text=Create folder');
      await count(
        page,
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder',
        1
      );
    },
  },
  {
    description: 'delete',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder'
      );
      await page.keyboard.up('Control');
      await page.click('text=Delete folder');
      await count(page, 'text=Following file will be deleted:', 1);
      await page.click(
        '.components-modal__screen-overlay .components-button.is-primary'
      );
      await count(
        page,
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder',
        0
      );
    },
  },
]);
