import { expect, Page } from '@playwright/test';
import { saveAndReload, testType } from '../../../playwright-utils';

testType('date', '"date":"2023-03-14T11:14:03"', () => {
  return [
    {
      description: 'change date',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-date"]');
        await page.click(
          '.blockstudio-fields__field--date .components-datetime__date__day >> nth=0'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check date',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-date"]');
        await expect(
          page.locator(
            '.blockstudio-fields__field--date [aria-label~="Selected"]'
          )
        ).toHaveText('1');
      },
    },
  ];
});
