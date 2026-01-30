import { Page } from '@playwright/test';
import { count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('color-populate', false, () => {
  return [
    {
      description: 'check color populate',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-color-populate"]');
        await count(page, '.components-circular-option-picker__option', 3);
      },
    },
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
        await page.click('[data-type="blockstudio/type-color-populate"]');
        await count(
          page,
          '.blockstudio-fields__field--color [aria-label="blue"]',
          1
        );
      },
    },
  ];
});
