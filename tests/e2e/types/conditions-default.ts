import { Page, expect } from '@playwright/test';
import { count, delay, testType } from '../utils/playwright-utils';

testType('conditions-default', false, () => {
  return [
    {
      description:
        'conditional field should show based on default value on first load',
      testFunction: async (page: Page) => {
        // Click the block to select it
        await page.click('[data-type="blockstudio/type-conditions-default"]');
        await delay(500);

        // The select field should show "gradient" as the selected value (the default)
        const selectValue = await page.locator(
          '.blockstudio-inspector select'
        );
        await expect(selectValue).toHaveValue('gradient');

        // The gradient field should be visible (conditioned on backgroundType == "gradient")
        await count(page, 'text=Background Gradient', 1);

        // The color field should NOT be visible (conditioned on backgroundType == "color")
        await count(page, 'text=Background Color', 0);

        // The image field should NOT be visible (conditioned on backgroundType == "image")
        await count(page, 'text=Background Image', 0);
      },
    },
    {
      description: 'switching select value should show correct conditional field',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-conditions-default"]');
        await delay(300);

        // Change to "color"
        await page.selectOption('.blockstudio-inspector select', 'color');
        await delay(300);

        // Now color field should be visible, gradient should not
        await count(page, 'text=Background Color', 1);
        await count(page, 'text=Background Gradient', 0);
        await count(page, 'text=Background Image', 0);
      },
    },
  ];
});
