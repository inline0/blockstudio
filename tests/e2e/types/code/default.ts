import { expect, FrameLocator } from '@playwright/test';
import { click, press, saveAndReload, testType } from '../../utils/playwright-utils';

testType('code', '"code":".selector { display: block; }"', () => {
  return [
    {
      description: 'change code',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-code"]');
        await click(editor, '.cm-line');
        await press(editor, 'Meta+A');
        await press(editor, 'Backspace');
        await editor.locator('body').pressSequentially('.selector { display: none; }');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check code',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-code"]');
        await expect(editor.locator('.cm-line').nth(0)).toHaveText(
          '.selector { display: none; }'
        );
      },
    },
  ];
});
