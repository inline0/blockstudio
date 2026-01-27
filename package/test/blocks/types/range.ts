import { expect, Page } from '@playwright/test';
import { saveAndReload, testType } from '../../../playwright-utils';

testType('range', '"range":10,"rangeZero":0,"textOnZero":false', () => {
  return [
    {
      description: 'change range',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-range"]');
        await page.fill('.blockstudio-fields__field--range input', '100');
        await saveAndReload(page);
      },
    },
    {
      description: 'check range',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-range"]');
        await expect(
          page.locator('.blockstudio-fields__field--range input').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
