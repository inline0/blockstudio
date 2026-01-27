import { Page } from '@playwright/test';
import { count, testType } from '../../../playwright-utils';

testType('help', false, () => {
  return [
    {
      description: 'render help icons',
      testFunction: async (page: Page) => {
        await count(page, '.blockstudio-field__label-info', 20);
      },
    },
  ];
});
