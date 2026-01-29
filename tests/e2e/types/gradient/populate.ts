import { Page } from '@playwright/test';
import { click, count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('gradient-populate', false, () => {
  return [
    {
      description: 'check gradient populate',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-gradient-populate"]');
        await count(editor, '.components-circular-option-picker__option', 3);
      },
    },
    {
      description: 'change gradient',
      testFunction: async (editor: Page) => {
        await click(
          editor,
          '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check gradient',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-gradient-populate"]');
        await count(
          editor,
          '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]',
          1
        );
      },
    },
  ];
});
