import { FrameLocator } from '@playwright/test';
import { click, expect, fill, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('range', '"range":10,"rangeZero":0,"textOnZero":false', () => {
  return [
    {
      description: 'change range',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-range"]');
        await fill(editor, '.blockstudio-fields__field--range input', '100');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check range',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-range"]');
        await expect(
          locator(editor, '.blockstudio-fields__field--range input').nth(0)
        ).toHaveValue('100');
      },
    },
  ];
});
