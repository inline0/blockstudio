import { FrameLocator } from '@playwright/test';
import { click, expect, fill, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('datetime', '"datetime":"2023-03-14T11:14:03"', () => {
  return [
    {
      description: 'change time',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-datetime"]');
        await fill(
          editor,
          '.blockstudio-fields__field--datetime .components-datetime__time-field-hours-input input',
          '11'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check time',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-datetime"]');
        await expect(
          locator(
            editor,
            '.blockstudio-fields__field--datetime .components-datetime__time-field-hours-input input'
          )
        ).toHaveValue('11');
      },
    },
  ];
});
