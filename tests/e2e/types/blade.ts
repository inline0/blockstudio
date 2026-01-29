import { Page } from '@playwright/test';
import { click, expect, fill, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('blade', '"text":"Default value"', () => {
  return [
    {
      description: 'change text',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-blade"]');
        await fill(editor, '.blockstudio-fields__field--text input', '100');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check text',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-blade"]');
        await expect(
          locator(editor, '.blockstudio-fields__field--text input').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
