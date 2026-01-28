import { FrameLocator } from '@playwright/test';
import { count, save, testType } from '../utils/playwright-utils';

testType('post-meta', false, () => {
  return [
    {
      description: 'block refreshes on save',
      testFunction: async (editor: FrameLocator) => {
        await save(editor);
        await count(editor, '.blockstudio-test__block--refreshed', 1);
      },
    },
  ];
});
