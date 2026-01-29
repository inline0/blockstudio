import { Page } from '@playwright/test';
import { click, expect, fill, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('range', '"range":10,"rangeZero":0,"textOnZero":false', () => {
  return [
    {
      description: 'change range',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-range"]');
        // Use the number input (not the range slider) to set value
        const rangeInput = editor.locator('.blockstudio-fields__field--range input[type="number"]').first();
        await rangeInput.fill('100');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check range',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-range"]');
        const rangeInput = editor.locator('.blockstudio-fields__field--range input[type="number"]').first();
        await expect(rangeInput).toHaveValue('100');
      },
    },
  ];
});
