import { Page } from '@playwright/test';
import {
  count,
  saveAndReload,
  testType,
  text,
} from '../../../playwright-utils';

testType('token', '"token":[{"value":"three","label":"Three"}]', () => {
  return [
    {
      description: 'change tokens',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-token"]');
        await page.click(
          '.blockstudio-fields__field--token .components-form-token-field__input-container'
        );
        await page.press('body', 'Backspace');
        await page.press('body', 'Backspace');
        for (const tokenValue of ['one', 'two']) {
          await page.fill(
            '.blockstudio-fields__field--token input',
            tokenValue
          );
          await page.press('body', 'Enter');
        }
        await saveAndReload(page);
      },
    },
    {
      description: 'check tokens',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-token"]');
        await count(
          page,
          '.blockstudio-fields__field--token .components-form-token-field__token',
          2
        );
        await text(
          page,
          '"token":[{"value":"one","label":"One"},{"value":"two","label":"Two"}]'
        );
      },
    },
  ];
});
