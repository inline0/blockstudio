import { Page } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('transforms-2', '"text":"Tester"', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-transforms-2"]');
        await page.click(
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await count(page, 'text=Native Transforms 1', 2);
      },
    },
  ];
});
