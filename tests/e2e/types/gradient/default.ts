import { Page } from '@playwright/test';
import { count, saveAndReload, testType } from '../../utils/playwright-utils';

testType(
  'gradient',
  '"gradient":{"name":"JShine","value":"linear-gradient(135deg,#12c2e9 0%,#c471ed 50%,#f64f59 100%)","slug":"jshine"}',
  () => {
    return [
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
          await page.click('[data-type="blockstudio/type-gradient"]');
          await count(
            page,
            '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]',
            1
          );
        },
      },
    ];
  }
);
