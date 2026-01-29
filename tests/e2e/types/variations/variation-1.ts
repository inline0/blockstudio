import { Page } from '@playwright/test';
import { click, count, testType } from '../../utils/playwright-utils';

testType('variations', '"select":"variation-1"', () => {
  return [
    {
      description: 'check values',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-variations"]');
        await count(editor, '.blockstudio-fields input', 0);
      },
    },
  ];
});
