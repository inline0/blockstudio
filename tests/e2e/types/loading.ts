import { FrameLocator } from '@playwright/test';
import { click, count, testType } from '../utils/playwright-utils';

testType('loading', false, () => {
  return [
    {
      description: 'check loading state',
      testFunction: async (editor: FrameLocator) => {
        await count(editor, 'text=blockstudio/type-loading', 1);
      },
    },
    {
      description: 'click element',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, 'text=blockstudio/type-loading');
        await count(editor, '.blockstudio-test__block', 1);
      },
    },
  ];
});
