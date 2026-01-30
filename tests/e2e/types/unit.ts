import { Page, expect } from '@playwright/test';
import {
  count,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType('unit', '"unit":"1rem"', () => {
  return [
    {
      description: 'has rem from default',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-unit"]');
        await count(page, '.blockstudio-fields__field--unit option', 4);
        await count(page, '.blockstudio-fields__field--unit [value="rem"]', 1);
      },
    },
    {
      description: 'change unit and data',
      testFunction: async (page: Page) => {
        await page
          .locator('.blockstudio-fields__field--unit select')
          .selectOption({ index: 1 });
        await page.fill('.blockstudio-fields__field--unit input', '10');
        await text(page, '"unit":"1rem"');
        await saveAndReload(page);
      },
    },
    {
      description: 'check unit and data',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-unit"]');
        await expect(
          page.locator('.blockstudio-fields__field--unit input')
        ).toHaveValue('10');
        await count(page, '.blockstudio-fields__field--unit option', 3);
        await text(page, '"unit":"10px"');
      },
    },
  ];
});
