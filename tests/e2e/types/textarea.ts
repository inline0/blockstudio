import { expect, Page, Frame } from '@playwright/test';
import { saveAndReload, testType } from '../utils/playwright-utils';

testType('textarea', '"textarea":"Default value"', () => {
  return [
    {
      description: 'change textarea',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-textarea"]');
        await page.fill('.blockstudio-fields__field--textarea textarea', '100');
        await saveAndReload(page);
      },
    },
    {
      description: 'check textarea',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-textarea"]');
        await expect(
          page.locator('.blockstudio-fields__field--textarea textarea').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
