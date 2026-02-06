import { Page, Frame } from '@playwright/test';
import { count, testType } from '../utils/playwright-utils';

testType('loading', false, () => {
  return [
    {
      description: 'check loading state',
      testFunction: async (_page: Page, canvas: Frame) => {
        await count(canvas, 'text=blockstudio/type-loading', 1);
      },
    },
    {
      description: 'click element',
      testFunction: async (_page: Page, canvas: Frame) => {
        await canvas.click('text=blockstudio/type-loading');
        await count(canvas, '.blockstudio-test__block', 1);
      },
    },
  ];
});
