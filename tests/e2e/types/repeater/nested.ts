import { Page } from '@playwright/test';
import { click, countText, delay, testType } from '../../utils/playwright-utils';

testType(
  'repeater-nested',
  false,
  () => {
    return Array.from({ length: 9 }).map((_, index) => {
      return {
        description: `add media ${index}`,
        testFunction: async (editor: Page) => {
          if (
            index === 0 ||
            index === 2 ||
            index === 4 ||
            index === 6 ||
            index === 8
          ) {
            await delay(1000);
            await editor.locator(`text=Open Media Library`).nth(index).click();
          }
          await delay(1000);
          await editor.locator(`text=Open Media Library`).nth(index).click();
          await click(editor, '#menu-item-browse:visible');
          // Use modifiers option for multi-select instead of separate press/release
          await editor.locator('li[data-id="1604"]:visible').click({ modifiers: ['Meta'] });
          await editor.locator('li[data-id="1605"]:visible').click({ modifiers: ['Meta'] });
          await delay(1000);
          await click(editor, '.media-frame-toolbar button:visible');
          await countText(editor, '"ID":1605', index + 1);
        },
      };
    });
  }
);
