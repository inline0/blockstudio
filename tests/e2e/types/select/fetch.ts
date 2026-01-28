import { FrameLocator } from '@playwright/test';
import { click, count, delay, fill, press, testType, text } from '../../utils/playwright-utils';

testType('select-fetch', '"select":false', () => {
  return [
    {
      description: 'add test entry',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-select-fetch"]');
        await fill(
          editor,
          '.blockstudio-fields .components-combobox-control__input',
          'test'
        );
        await count(editor, '.components-form-token-field__suggestion', 1);
        await delay(5000);
        await press(editor, 'ArrowDown');
        await press(editor, 'Enter');
        await text(editor, '"select":[{"label":"Test","value":560}]');
      },
    },
    {
      description: 'add second entry',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-select-fetch"]');
        await fill(
          editor,
          '.blockstudio-fields .components-combobox-control__input',
          'e'
        );
        await count(editor, '.components-form-token-field__suggestion', 9);
        await press(editor, 'Enter');
        await text(
          editor,
          '"select":[{"label":"Test","value":560},{"label":"Et adipisci quia aut","value":533}]'
        );
      },
    },
    {
      description: 'reorder',
      testFunction: async (editor: FrameLocator) => {
        await editor.locator('[data-rfd-draggable-id]').nth(0).focus();
        await press(editor, 'ArrowDown');
        await text(
          editor,
          '"select":[{"label":"Et adipisci quia aut","value":533},{"label":"Test","value":560}]'
        );
      },
    },
  ];
});
