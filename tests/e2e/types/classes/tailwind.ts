import { Page } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('classes-tailwind', '"classes":"text-red-500"', () => {
  return [
    {
      description: 'add class',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.fill('.components-form-token-field input', 'bg-');
        await count(page, 'text=bg-amber-100', 1);
      },
    },
  ];
});
