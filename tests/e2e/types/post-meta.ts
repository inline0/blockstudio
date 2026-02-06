import { Page, Frame } from '@playwright/test';
import { count, save, testType } from '../utils/playwright-utils';

testType('post-meta', false, () => {
  return [
    {
      description: 'block refreshes on save',
      testFunction: async (page: Page, canvas: Frame) => {
        await save(page);
        await count(canvas, '.blockstudio-test__block--refreshed', 1);
      },
    },
  ];
});
