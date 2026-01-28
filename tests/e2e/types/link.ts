import { FrameLocator } from '@playwright/test';
import { click, count, fill, saveAndReload, testType } from '../utils/playwright-utils';

testType(
  'link',
  '"link":{"id":"google.com","title":"google.com","url":"https:\\/\\/google.com","type":"URL"}',
  () => {
    return [
      {
        description: 'change link',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, '[data-type="blockstudio/type-link"]');
          await click(
            editor,
            '.blockstudio-fields__field--link .components-button'
          );
          const edit = editor.locator('[aria-label="Edit link"]');
          if (await edit.isVisible().catch(() => false)) {
            await edit.click();
          }
          await fill(
            editor,
            '.block-editor-link-control__text-content .components-text-control__input',
            'Blockstudio'
          );
          await fill(editor, '[value="https://google.com"]', 'blockstudio.dev');
          await click(editor, '.block-editor-link-control__search-submit');
          await click(editor, '.components-modal__header [aria-label="Close"]');
          await saveAndReload(editor);
        },
      },
      {
        description: 'check link',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, '[data-type="blockstudio/type-link"]');
          await count(editor, 'text=blockstudio.dev', 1);
        },
      },
    ];
  }
);
