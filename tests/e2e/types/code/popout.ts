import { expect, Page, Frame } from '@playwright/test';
import { testType } from '../../utils/playwright-utils';

testType('code-popout', '"code":".selector { display: block; }"', () => {
  return [
    {
      description: 'popout button is visible',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-code-popout"]');
        const popoutButton = page.locator('.blockstudio-fields__action');
        await expect(popoutButton).toBeVisible();
      },
    },
    {
      description: 'popout opens window and syncs changes',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-code-popout"]');

        const [popup] = await Promise.all([
          page.waitForEvent('popup'),
          page.click('.blockstudio-fields__action'),
        ]);

        await popup.waitForLoadState();
        await popup.locator('.cm-line').first().click();
        await popup.keyboard.press('ControlOrMeta+A');
        await popup.keyboard.press('Backspace');
        await popup.keyboard.type('.changed { color: red; }');

        await expect(page.locator('.blockstudio-fields__field--code .cm-line').nth(0)).toHaveText(
          '.changed { color: red; }'
        );

        await popup.close();
      },
    },
  ];
});
