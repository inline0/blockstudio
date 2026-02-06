import { Page, expect, Frame } from '@playwright/test';
import { saveAndReload, testType } from '../../utils/playwright-utils';

testType('storage-option', false, () => {
  return [
    {
      description: 'fill and save option fields',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-storage-option"]');
        await page.fill('[data-id="option_text"] input', 'option test value');
        await page.fill('[data-id="all_storage"] input', 'all storage test');
        await saveAndReload(page);
      },
    },
    {
      description: 'check values persist after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-storage-option"]');
        await expect(
          page.locator('[data-id="option_text"] input')
        ).toHaveValue('option test value');
        await expect(
          page.locator('[data-id="all_storage"] input')
        ).toHaveValue('all storage test');
      },
    },
  ];
});
