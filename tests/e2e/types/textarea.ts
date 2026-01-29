import { Page } from '@playwright/test';
import { click, expect, fill, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('textarea', '"textarea":"Default value"', () => {
  return [
    {
      description: 'change textarea',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-textarea"]');
        await fill(editor, '.blockstudio-fields__field--textarea textarea', '100');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check textarea',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-textarea"]');
        await expect(
          locator(editor, '.blockstudio-fields__field--textarea textarea').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
