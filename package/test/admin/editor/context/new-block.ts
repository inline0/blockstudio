import { Page, test } from '@playwright/test';
import { count, testContext, delay } from '../../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

testContext('new block', () => [
  {
    description: 'create folder',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element'
      );
      await page.keyboard.up('Control');
      await page.click('text=New folder');
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
    description: 'name exists',
    testFunction: async (page: Page) => {
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder'
      );
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder'
      );
      await page.keyboard.up('Control');
      await page.click('text=New block');
      await delay(1000);
      await page.keyboard.type('blockstudio-element/code');
      await count(page, 'text=Block name already exists', 1);
    },
  },
  {
    description: 'create php',
    testFunction: async (page: Page) => {
      for (let i = 1; i <= 24; i++) {
        await page.keyboard.press('Backspace', {
          delay: 100,
        });
      }
      await delay(1000);
      await page.keyboard.type('test/block');
      await page.click('text=Create block');
      await count(page, '.components-modal__screen-overlay', 0);
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder'
      );
      await count(
        page,
        '#block-plugins-blockstudio-includes-library-blockstudio-element-test-folder-block-json',
        1
      );
    },
  },
  {
    description: 'delete php',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-test-folder-block-json'
      );
      await page.keyboard.up('Control');
      await page.click('text=Delete block');
      await count(page, 'text=Warning: ', 1);
      await page.click(
        '.components-modal__screen-overlay .components-button.is-primary'
      );
      await count(
        page,
        '#block-plugins-blockstudio-includes-library-blockstudio-element-test-folder-block-json',
        0
      );
    },
  },
  {
    description: 'create twig',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#folder-plugins-blockstudio-includes-library-blockstudio-element-test-folder'
      );
      await page.keyboard.up('Control');
      await page.click('text=New block');
      await delay(1000);
      await page.keyboard.type('test/block');
      await page.getByLabel('Twig').check();
      await page.click('text=Create block');
      await count(page, '.components-modal__screen-overlay', 0);
      await count(
        page,
        '#block-plugins-blockstudio-includes-library-blockstudio-element-test-folder-index-twig',
        1
      );
    },
  },
  {
    description: 'delete twig',
    testFunction: async (page: Page) => {
      await page.keyboard.down('Control');
      await page.click(
        '#block-plugins-blockstudio-includes-library-blockstudio-element-test-folder-index-twig'
      );
      await page.keyboard.up('Control');
      await page.click('text=Delete block');
      await count(page, 'text=Warning: ', 1);
      await page.click(
        '.components-modal__screen-overlay .components-button.is-primary'
      );
      await count(
        page,
        '#block-plugins-blockstudio-includes-library-blockstudio-element-test-folder-index-twig',
        0
      );
    },
  },
  {
    description: 'delete folder',
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
