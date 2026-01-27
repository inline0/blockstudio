import { Page } from '@playwright/test';
import { count, testType } from '../../../../playwright-utils';

testType(
  'tabs-nested',
  '"toggle":false,"toggle2":false,"toggle3":false,"text2":false,"text":false',
  () => {
    return [
      {
        description: 'nested tabs exist',
        testFunction: async (page: Page) => {
          await count(page, 'text=Tab 1', 2);
          await count(page, 'text=Tab 2', 2);
        },
      },
    ];
  }
);
