import { Page, expect } from '@playwright/test';
import { saveAndReload, testType } from '../utils/playwright-utils';

testType('blade', '"text":"Default value"', () => {
  return [
    {
      description: 'change text',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-blade"]');
        await page.fill('.blockstudio-fields__field--text input', '100');
        await saveAndReload(page);
      },
    },
    {
      description: 'check text',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-blade"]');
        await expect(
          page.locator('.blockstudio-fields__field--text input').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
