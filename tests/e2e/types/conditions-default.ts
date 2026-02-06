import { Page, expect, Frame } from '@playwright/test';
import { count, delay, testType } from '../utils/playwright-utils';

testType('conditions-default', false, () => {
  return [
    {
      description:
        'conditional field should show based on default value on first load',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-conditions-default"]');
        await delay(500);

        const selectValue = page.locator(
          '.blockstudio-fields__field--select select'
        );
        await expect(selectValue).toHaveValue('gradient');

        await count(page, 'text=Background Gradient', 1);
        await count(page, 'text=Background Color', 0);
        await count(page, 'text=Background Image', 0);
      },
    },
    {
      description: 'switching select value should show correct conditional field',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-conditions-default"]');
        await delay(300);

        await page.selectOption(
          '.blockstudio-fields__field--select select',
          'color'
        );
        await delay(300);

        await count(page, 'text=Background Color', 1);
        await count(page, 'text=Background Gradient', 0);
        await count(page, 'text=Background Image', 0);
      },
    },
  ];
});
