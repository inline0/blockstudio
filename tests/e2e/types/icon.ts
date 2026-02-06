import { expect, Page, Frame } from '@playwright/test';
import {
  count,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType(
  'icon',
  '"icon":{"set":"octicons","icon":"alert-16","element":',
  () => {
    return [
      {
        description: 'two svg',
        testFunction: async (_page: Page, canvas: Frame) => {
          await count(canvas, '#blockstudio-type-icon svg', 2);
        },
      },
      {
        description: 'change icon',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-icon"]');
          await page
            .locator(
              '.blockstudio-fields__field--icon .components-combobox-control__input'
            )
            .last()
            .click();
          await page
            .locator(
              '.blockstudio-fields__field--icon .components-form-token-field__suggestion'
            )
            .nth(0)
            .click();
          await saveAndReload(page);
        },
      },
      {
        description: 'check icon',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-icon"]');
          await text(page, 'accessibility-16');
        },
      },
      {
        description: 'check repeater',
        testFunction: async (page: Page, _canvas: Frame) => {
          await page.click('text=Add an Icon');
          await page.click('text=Add an Icon');
          await page
            .locator('.blockstudio-fields .components-combobox-control__input')
            .nth(3)
            .click();
          await page
            .locator(
              '.blockstudio-fields .components-form-token-field__suggestion'
            )
            .nth(0)
            .click();
          await page.locator('.blockstudio-repeater__remove').nth(1).click();
          await expect(
            page.locator('.components-form-token-field__input').nth(2)
          ).not.toHaveValue('0');
        },
      },
    ];
  }
);
