import { expect, Page } from '@playwright/test';
import { saveAndReload, testType } from '../utils/playwright-utils';

testType('toggle', '"toggle":true', () => {
  return [
    {
      description: 'change toggle',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-toggle"]');
        await page.uncheck('.blockstudio-fields__field--toggle input');
        await saveAndReload(page);
      },
    },
    {
      description: 'check toggle',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-toggle"]');
        await expect(
          page.locator('.blockstudio-fields__field--toggle input')
        ).not.toBeChecked();
      },
    },
  ];
});
