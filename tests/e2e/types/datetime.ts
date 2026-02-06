import { expect, Page, Frame } from '@playwright/test';
import { saveAndReload, testType } from '../utils/playwright-utils';

testType('datetime', '"datetime":"2023-03-14T11:14:03"', () => {
  return [
    {
      description: 'change time',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-datetime"]');
        await page.fill(
          '.blockstudio-fields__field--datetime .components-datetime__time-field-hours-input input',
          '11'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check time',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-datetime"]');
        await expect(
          page.locator(
            '.blockstudio-fields__field--datetime .components-datetime__time-field-hours-input input'
          )
        ).toHaveValue('11');
      },
    },
  ];
});
