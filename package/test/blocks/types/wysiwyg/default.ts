import { Page } from '@playwright/test';
import { count, testType } from '../../../../playwright-utils';

testType('wysiwyg', 'Default text', () => [
  {
    description: 'type text',
    testFunction: async (page: Page) => {
      await page.click('.blockstudio-fields .ProseMirror');
      await page.keyboard.type('TEST', { delay: 1000 });
      await count(page, '#blockstudio-type-wysiwyg p', 1);
      await page.keyboard.press('Meta+A');
    },
  },
  {
    description: 'toolbar',
    testFunction: async (page: Page) => {
      await count(
        page,
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
      testFunction: async (page: Page) => {
        await count(
          page,
          `.blockstudio-fields__field--wysiwyg [aria-label="${tag}"]`,
          1
        );
      },
    },
    {
      description: `${tag} can be pressed`,
      testFunction: async (page: Page) => {
        await page.click(`.blockstudio-fields [aria-label="${tag}"]`);
        await count(
          page,
          `.blockstudio-fields [aria-label="${tag}"][aria-pressed="true"]`,
          1
        );
        if (!tag.includes('Align')) {
          await page.click(`.blockstudio-fields [aria-label="${tag}"]`);
          await count(
            page,
            `.blockstudio-fields [aria-label="${tag}"][aria-pressed="true"]`,
            0
          );
        }
      },
    },
  ]),
]);
