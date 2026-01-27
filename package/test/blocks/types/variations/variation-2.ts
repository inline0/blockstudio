import { Page } from '@playwright/test';
import { count, testType } from '../../../../playwright-utils';

testType('variations\\/variation-2', '"select":"variation-2"', () => {
  return [
    {
      description: 'check values',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-variations"]');
        await count(page, '.blockstudio-fields input', 0);
      },
    },
  ];
});
