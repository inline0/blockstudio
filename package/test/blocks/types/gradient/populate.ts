import { Page } from '@playwright/test';
import { count, saveAndReload, testType } from '../../../../playwright-utils';

testType('gradient-populate', false, () => {
  return [
    {
      description: 'check gradient populate',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-gradient-populate"]');
        await count(page, '.components-circular-option-picker__option', 3);
      },
    },
    {
      description: 'change gradient',
      testFunction: async (page: Page) => {
        await page.click(
          '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check gradient',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-gradient-populate"]');
        await count(
          page,
          '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]',
          1
        );
      },
    },
  ];
});
