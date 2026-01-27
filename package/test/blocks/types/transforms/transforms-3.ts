import { Page } from '@playwright/test';
import { count, testType } from '../../../../playwright-utils';

testType('transforms-3', '"text":19', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-transforms-3"]');
        await page.click(
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await count(page, 'text=Native Transforms 1', 1);
        await count(page, 'text=Native Transforms 2', 1);
      },
    },
    {
      description: 'regex transform',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-transforms-3"]');
        await page.keyboard.press('Enter');
        await page.keyboard.press('-');
        await page.keyboard.press('-');
        await page.keyboard.press('-');
        await page.keyboard.press('Enter');
        await page
          .locator('[data-type="blockstudio/type-transforms-3"]')
          .nth(1)
          .click();
        await count(page, '[data-type="blockstudio/type-transforms-3"]', 2);
      },
    },
    {
      description: 'prefix transform',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-transforms-3"]');
        await page.keyboard.press('Enter');
        await page.keyboard.press('?');
        await page.keyboard.press('?');
        await page.keyboard.press('?');
        await page.keyboard.press('Space');
        await count(page, '[data-type="blockstudio/type-transforms-3"]', 3);
      },
    },
  ];
});
