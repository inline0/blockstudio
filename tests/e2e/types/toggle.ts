import { FrameLocator } from '@playwright/test';
import { click, expect, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('toggle', '"toggle":true', () => {
  return [
    {
      description: 'change toggle',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-toggle"]');
        await editor.locator('.blockstudio-fields__field--toggle input').uncheck();
        await saveAndReload(editor);
      },
    },
    {
      description: 'check toggle',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-toggle"]');
        await expect(
          locator(editor, '.blockstudio-fields__field--toggle input')
        ).not.toBeChecked();
      },
    },
  ];
});
