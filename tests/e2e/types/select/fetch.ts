import { Page, Frame } from '@playwright/test';
import { count, delay, testType, text } from '../../utils/playwright-utils';

testType('select-fetch', '"select":false', () => {
  return [
    {
      description: 'add test entry',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-select-fetch"]');
        await page.fill(
          '.blockstudio-fields .components-combobox-control__input',
          'test'
        );
        await count(page, '.components-form-token-field__suggestion', 1);
        await delay(5000);
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        await text(canvas, '"select":[{"label":"Test","value":560}]');
      },
    },
    {
      description: 'add second entry',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-select-fetch"]');
        await page.fill(
          '.blockstudio-fields .components-combobox-control__input',
          'e'
        );
        await count(page, '.components-form-token-field__suggestion', 9);
        await page.keyboard.press('Enter');
        await text(
            canvas,
          '"select":[{"label":"Test","value":560},{"label":"Et adipisci quia aut","value":533}]'
        );
      },
    },
    {
      description: 'reorder',
      testFunction: async (page: Page, canvas: Frame) => {
        await page.locator('[data-rfd-draggable-id]').nth(0).focus();
        await page.keyboard.press('ArrowDown');
        await text(
            canvas,
          '"select":[{"label":"Et adipisci quia aut","value":533},{"label":"Test","value":560}]'
        );
      },
    },
  ];
});
