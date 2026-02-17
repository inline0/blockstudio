import { expect, Page, Frame } from '@playwright/test';
import { saveAndReload, testType } from '../../utils/playwright-utils';

testType('code', '"code":".selector { display: block; }"', () => {
  return [
    {
      description: 'change code',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-code"]');
        await page.locator('.blockstudio-fields__field--code .cm-line').first().click();
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type('.selector { display: none; }');
        await saveAndReload(page);
      },
    },
    {
      description: 'check code',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-code"]');
        await expect(page.locator('.blockstudio-fields__field--code .cm-line').first()).toHaveText(
          '.selector { display: none; }'
        );
      },
    },
  ];
});
