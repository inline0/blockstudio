import { FrameLocator } from '@playwright/test';
import { click, count, testType } from '../../utils/playwright-utils';

testType('variations\\/variation-2', '"select":"variation-2"', () => {
  return [
    {
      description: 'check values',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-variations"]');
        await count(editor, '.blockstudio-fields input', 0);
      },
    },
  ];
});
