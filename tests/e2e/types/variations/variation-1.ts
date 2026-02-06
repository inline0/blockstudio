import { Page, Frame } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('variations', '"select":"variation-1"', () => {
  return [
    {
      description: 'check values',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-variations"]');
        await count(page, '.blockstudio-fields input', 0);
      },
    },
  ];
});
