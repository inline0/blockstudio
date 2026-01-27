import { Page, test } from '@playwright/test';
import { count, delay, testContext } from '../../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

testContext('new file', () => [
  {
    description: 'name exists',
    testFunction: async (page: Page) => {
      await page.click('#instance-plugins-blockstudio-package-test-blocks');
      await page.keyboard.down('Control');
      await page.click('#instance-plugins-blockstudio-package-test-blocks');
      await page.keyboard.up('Control');
      await page.click('text=New file');
      await delay(1000);
      await page.keyboard.type('_script.js');
      await count(page, 'text=File name already exists', 1);
    },
  },
  {
    description: 'create',
    testFunction: async (page: Page) => {
      for (let i = 1; i <= 10; i++) {
        await page.keyboard.press('Backspace', {
          delay: 100,
        });
      }
      await delay(1000);
      await page.keyboard.type('test.css');
      await page.click('text=Create file');
      await count(
        page,
        '#block-plugins-blockstudio-package-test-blocks-test-css',
        1
      );
    },
  },
  {
    description: 'delete',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-package-test-blocks-test-css'
      );
      await page.keyboard.up('Control');
      await page.click(
        '#block-plugins-blockstudio-package-test-blocks-test-css .blockstudio-add .components-button'
      );
      await page.click('text=Delete file');
      await count(page, 'text=Following file will be deleted:', 1);
      await page.click(
        '.components-modal__screen-overlay .components-button.is-primary'
      );
      await count(
        page,
        '#block-plugins-blockstudio-package-test-blocks-test-css',
        0
      );
    },
  },
]);
