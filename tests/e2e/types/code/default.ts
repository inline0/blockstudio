import { expect, Page } from '@playwright/test';
import { saveAndReload, testType } from '../../utils/playwright-utils';

testType('code', '"code":".selector { display: block; }"', () => {
  return [
    {
      description: 'change code',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-code"]');
        await page.click('.cm-line');
        await page.keyboard.press('Meta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type('.selector { display: none; }');
        await saveAndReload(page);
      },
    },
    {
      description: 'check code',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-code"]');
        await expect(page.locator('.cm-line').nth(0)).toHaveText(
          '.selector { display: none; }'
        );
      },
    },
  ];
});
