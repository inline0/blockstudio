import { Page } from '@playwright/test';
import { click, count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('color', '"color":{"name":"red","value":"#f00","slug":"red"}', () => {
  return [
    {
      description: 'change color',
      testFunction: async (editor: Page) => {
        // WordPress 6.7 color picker uses option elements with "Color: X" aria-label
        await click(
          editor,
          '.blockstudio-fields__field--color [aria-label="Color: blue"]'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check color',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-color"]');
        await count(
          editor,
          '.blockstudio-fields__field--color [aria-label="Color: blue"][aria-selected="true"]',
          1
        );
      },
    },
  ];
});
