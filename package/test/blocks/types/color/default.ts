import { Page } from '@playwright/test';
import { count, saveAndReload, testType } from '../../../../playwright-utils';

testType('color', '"color":{"name":"red","value":"#f00","slug":"red"}', () => {
  return [
    {
      description: 'change color',
      testFunction: async (page: Page) => {
        await page.click(
          '.blockstudio-fields__field--color [aria-label="blue"]'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check color',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-color"]');
        await count(
          page,
          '.blockstudio-fields__field--color [aria-label="blue"]',
          1
        );
      },
    },
  ];
});
