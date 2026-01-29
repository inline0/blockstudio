import { Page } from '@playwright/test';
import { click, count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('color-populate', false, () => {
  return [
    {
      description: 'check color populate',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-color-populate"]');
        await count(editor, '.components-circular-option-picker__option', 3);
      },
    },
    {
      description: 'change color',
      testFunction: async (editor: Page) => {
        // WordPress 6.7 color picker uses "Color: X" aria-label
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
        await click(editor, '[data-type="blockstudio/type-color-populate"]');
        await count(
          editor,
          '.blockstudio-fields__field--color [aria-label="Color: blue"][aria-selected="true"]',
          1
        );
      },
    },
  ];
});
