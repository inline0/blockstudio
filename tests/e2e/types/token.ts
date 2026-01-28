import { FrameLocator } from '@playwright/test';
import {
  click,
  count,
  fill,
  press,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType('token', '"token":[{"value":"three","label":"Three"}]', () => {
  return [
    {
      description: 'change tokens',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-token"]');
        await click(
          editor,
          '.blockstudio-fields__field--token .components-form-token-field__input-container'
        );
        await press(editor, 'Backspace');
        await press(editor, 'Backspace');
        for (const tokenValue of ['one', 'two']) {
          await fill(
            editor,
            '.blockstudio-fields__field--token input',
            tokenValue
          );
          await press(editor, 'Enter');
        }
        await saveAndReload(editor);
      },
    },
    {
      description: 'check tokens',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-token"]');
        await count(
          editor,
          '.blockstudio-fields__field--token .components-form-token-field__token',
          2
        );
        await text(
          editor,
          '"token":[{"value":"one","label":"One"},{"value":"two","label":"Two"}]'
        );
      },
    },
  ];
});
