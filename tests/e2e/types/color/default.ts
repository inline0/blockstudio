import { FrameLocator } from '@playwright/test';
import { click, count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('color', '"color":{"name":"red","value":"#f00","slug":"red"}', () => {
  return [
    {
      description: 'change color',
      testFunction: async (editor: FrameLocator) => {
        await click(
          editor,
          '.blockstudio-fields__field--color [aria-label="blue"]'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check color',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-color"]');
        await count(
          editor,
          '.blockstudio-fields__field--color [aria-label="blue"]',
          1
        );
      },
    },
  ];
});
