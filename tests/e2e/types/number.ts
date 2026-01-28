import { expect, Page } from '@playwright/test';
import { saveAndReload, testType } from '../utils/playwright-utils';

testType('number', '"number":10,"numberZero":0,"textOnZero":false', () => {
  return [
    {
      description: 'change number',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-number"]');
        await page.fill('.blockstudio-fields__field--number input', '100');
        await saveAndReload(page);
      },
    },
    {
      description: 'check number',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-number"]');
        await expect(
          page.locator('.blockstudio-fields__field--number input').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
