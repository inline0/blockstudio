import { FrameLocator } from '@playwright/test';
import { count, testType } from '../utils/playwright-utils';

testType('help', false, () => {
  return [
    {
      description: 'render help icons',
      testFunction: async (editor: FrameLocator) => {
        await count(editor, '.blockstudio-field__label-info', 20);
      },
    },
  ];
});
