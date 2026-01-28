import { FrameLocator } from '@playwright/test';
import { click, expect, locator, saveAndReload, testType } from '../utils/playwright-utils';

testType('date', '"date":"2023-03-14T11:14:03"', () => {
  return [
    {
      description: 'change date',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-date"]');
        await click(
          editor,
          '.blockstudio-fields__field--date .components-datetime__date__day >> nth=0'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check date',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-date"]');
        await expect(
          locator(
            editor,
            '.blockstudio-fields__field--date [aria-label~="Selected"]'
          )
        ).toHaveText('1');
      },
    },
  ];
});
