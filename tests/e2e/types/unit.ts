import { FrameLocator } from '@playwright/test';
import {
  click,
  count,
  expect,
  fill,
  locator,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType('unit', '"unit":"1rem"', () => {
  return [
    {
      description: 'has rem from default',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-unit"]');
        await count(editor, '.blockstudio-fields__field--unit option', 4);
        await count(editor, '.blockstudio-fields__field--unit [value="rem"]', 1);
      },
    },
    {
      description: 'change unit and data',
      testFunction: async (editor: FrameLocator) => {
        await editor
          .locator('.blockstudio-fields__field--unit select')
          .selectOption({ index: 1 });
        await fill(editor, '.blockstudio-fields__field--unit input', '10');
        await text(editor, '"unit":"1rem"');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check unit and data',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-unit"]');
        await expect(
          locator(editor, '.blockstudio-fields__field--unit input')
        ).toHaveValue('10');
        await count(editor, '.blockstudio-fields__field--unit option', 3);
        await text(editor, '"unit":"10px"');
      },
    },
  ];
});
