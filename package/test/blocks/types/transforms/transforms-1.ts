import { Page } from '@playwright/test';
import { count, testType } from '../../../../playwright-utils';

testType('transforms-1', '"text":"Default value"', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-transforms-1"]');
        await page.click(
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await count(page, 'text=Native Transforms 2', 2);
      },
    },
  ];
});
