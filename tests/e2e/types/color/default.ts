import { Page, Frame } from '@playwright/test';
import { count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('color', '"color":{"name":"red","value":"#f00","slug":"red"}', () => {
  return [
    {
      description: 'change color',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click(
          '.blockstudio-fields__field--color [aria-label="blue"]'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check color',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-color"]');
        await count(
          page,
          '.blockstudio-fields__field--color [aria-label="blue"]',
          1
        );
      },
    },
  ];
});
