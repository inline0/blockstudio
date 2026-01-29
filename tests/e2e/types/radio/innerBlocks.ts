import { Page } from '@playwright/test';
import { click, count, testType } from '../../utils/playwright-utils';

testType('radio-innerblocks', '"layout":false', () => {
  return [
    {
      description: 'layout 1',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-radio-innerblocks"]');
        await editor
          .locator('.blockstudio-fields .components-radio-control__input')
          .nth(0)
          .click();
        await count(editor, 'text=This is the left Heading Level 1.', 1);
        await count(editor, 'text=This is the right Heading Level 1.', 1);
        await count(editor, 'text=This is the left Paragraph Level 1.', 1);
        await count(editor, 'text=This is the right Paragraph Level 1.', 1);
      },
    },
    {
      description: 'layout 2',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-radio-innerblocks"]');
        await editor
          .locator('.blockstudio-fields .components-radio-control__input')
          .nth(1)
          .click();
        await count(editor, 'text=This is the left Heading Level 2.', 1);
        await count(editor, 'text=This is the right Heading Level 2.', 1);
        await count(editor, 'text=This is the left Paragraph Level 1.', 1);
        await count(editor, 'text=This is the right Paragraph Level 1.', 1);
      },
    },
  ];
});
