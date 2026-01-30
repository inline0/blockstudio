import { Page } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('classes', '"classes":"class-1 class-2"', () => {
  return [
    {
      description: 'add class',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes"]');
        await page.fill('.components-form-token-field input', 'is-');
        await count(page, 'text=is-dark-theme', 1);
      },
    },
  ];
});
