import { FrameLocator } from '@playwright/test';
import { click, count, testType } from '../../utils/playwright-utils';

testType('transforms-2', '"text":"Tester"', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-transforms-2"]');
        await click(
          editor,
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await count(editor, 'text=Native Transforms 1', 1);
      },
    },
  ];
});
