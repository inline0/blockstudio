import { Page } from '@playwright/test';
import { click, count, press, testType } from '../../utils/playwright-utils';

testType('wysiwyg', 'Default text', () => [
  {
    description: 'type text',
    testFunction: async (editor: Page) => {
      await click(editor, '.blockstudio-fields .ProseMirror');
      await editor.locator('body').pressSequentially('TEST', { delay: 1000 });
      await count(editor, '#blockstudio-type-wysiwyg p', 1);
      await press(editor, 'Meta+A');
    },
  },
  {
    description: 'toolbar',
    testFunction: async (editor: Page) => {
      await count(
        editor,
        '.blockstudio-fields__field--wysiwyg .components-select-control__input option',
        7
      );
    },
  },
  ...[
    'Bold',
    'Italic',
    'Underline',
    'Strikethrough',
    'Code',
    'Align left',
    'Align center',
    'Align right',
    'Align justify',
  ].flatMap((tag) => [
    {
      description: `${tag} exists`,
      testFunction: async (editor: Page) => {
        await count(
          editor,
          `.blockstudio-fields__field--wysiwyg [aria-label="${tag}"]`,
          1
        );
      },
    },
    {
      description: `${tag} can be pressed`,
      testFunction: async (editor: Page) => {
        await click(editor, `.blockstudio-fields [aria-label="${tag}"]`);
        await count(
          editor,
          `.blockstudio-fields [aria-label="${tag}"][aria-pressed="true"]`,
          1
        );
        if (!tag.includes('Align')) {
          await click(editor, `.blockstudio-fields [aria-label="${tag}"]`);
          await count(
            editor,
            `.blockstudio-fields [aria-label="${tag}"][aria-pressed="true"]`,
            0
          );
        }
      },
    },
  ]),
]);
