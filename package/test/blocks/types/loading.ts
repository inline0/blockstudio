import { Page } from '@playwright/test';
import { count, testType } from '../../../playwright-utils';

testType('loading', false, () => {
  return [
    {
      description: 'check loading state',
      testFunction: async (page: Page) => {
        await count(page, 'text=blockstudio/type-loading', 1);
      },
    },
    {
      description: 'click element',
      testFunction: async (page: Page) => {
        await page.click('text=blockstudio/type-loading');
        await count(page, '.blockstudio-test__block', 1);
      },
    },
  ];
});
