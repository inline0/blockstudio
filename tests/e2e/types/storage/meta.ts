import { Page, expect, Frame } from '@playwright/test';
import { saveAndReload, testType } from '../../utils/playwright-utils';

testType('storage-meta', false, () => {
  return [
    {
      description: 'fill and save meta fields',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-storage-meta"]');
        await page.fill('[data-id="meta_text"] input', 'meta test value');
        await page.fill('[data-id="block_and_meta"] input', 'both test value');
        await saveAndReload(page);
      },
    },
    {
      description: 'check values persist after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-storage-meta"]');
        await expect(
          page.locator('[data-id="meta_text"] input')
        ).toHaveValue('meta test value');
        await expect(
          page.locator('[data-id="block_and_meta"] input')
        ).toHaveValue('both test value');
      },
    },
  ];
});
