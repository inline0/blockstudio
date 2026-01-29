import { Page } from '@playwright/test';
import { click, count, press, testType } from '../../utils/playwright-utils';

testType('transforms-3', '"text":19', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-transforms-3"]');
        await click(
          editor,
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await count(editor, 'text=Native Transforms 1', 1);
        await count(editor, 'text=Native Transforms 2', 1);
      },
    },
    {
      description: 'regex transform',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-transforms-3"]');
        await press(editor, 'Enter');
        await press(editor, '-');
        await press(editor, '-');
        await press(editor, '-');
        await press(editor, 'Enter');
        await editor
          .locator('[data-type="blockstudio/type-transforms-3"]')
          .nth(1)
          .click();
        await count(editor, '[data-type="blockstudio/type-transforms-3"]', 2);
      },
    },
    {
      description: 'prefix transform',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-transforms-3"]');
        await press(editor, 'Enter');
        await press(editor, '?');
        await press(editor, '?');
        await press(editor, '?');
        await press(editor, 'Space');
        await count(editor, '[data-type="blockstudio/type-transforms-3"]', 3);
      },
    },
  ];
});
