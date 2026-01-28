import { FrameLocator } from '@playwright/test';
import { click, count, testType, text } from '../../utils/playwright-utils';

testType('select-innerblocks', '"layout":false', () => {
  return [
    {
      description: 'layout 1',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, 'text=ID: blockstudio-type-select-innerblocks');
        await editor
          .locator('.blockstudio-fields .components-select-control__input')
          .selectOption({
            value: '1',
          });
        await count(editor, 'text=This is the left Heading Level 1.', 1);
        await count(editor, 'text=This is the right Heading Level 1.', 1);
        await count(editor, 'text=This is the left Paragraph Level 1.', 1);
        await count(editor, 'text=This is the right Paragraph Level 1.', 1);
        await text(
          editor,
          '"text":"This is the left Text.","textClassSelect":"class-1"'
        );
        await click(editor, 'text=This is the left Heading Level 1.');
        await count(editor, '[aria-label="blue"][aria-selected="true"]', 1);
        await click(editor, 'text=This is the right Heading Level 1.');
        await count(editor, '[aria-label="blue"][aria-selected="true"]', 1);
      },
    },
    {
      description: 'layout 2',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, 'text=ID: blockstudio-type-select-innerblocks');
        await editor
          .locator('.blockstudio-fields .components-select-control__input')
          .selectOption({
            value: '2',
          });
        await count(editor, 'text=This is the left Heading Level 2.', 1);
        await count(editor, 'text=This is the right Heading Level 2.', 1);
        await count(editor, 'text=This is the left Paragraph Level 1.', 1);
        await count(editor, 'text=This is the right Paragraph Level 1.', 1);
        await text(
          editor,
          '"text":"This is the left Text.","textClassSelect":"class-1"'
        );
        await click(editor, 'text=This is the left Heading Level 2.');
        await count(editor, '[aria-label="blue"][aria-selected="true"]', 1);
        await click(editor, 'text=This is the right Heading Level 2.');
        await count(editor, '[aria-label="blue"][aria-selected="true"]', 1);
      },
    },
  ];
});
