import { expect, Page } from '@playwright/test';
import { testType } from '../../utils/playwright-utils';

testType('code-undo', '"code":".initial {}"', () => {
  return [
    {
      description: 'undo works within code field without triggering Gutenberg undo',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-code-undo"]');

        const codeMirror = page.locator('.blockstudio-fields__field--code .cm-content');
        await codeMirror.click();
        await page.keyboard.type('first');
        await page.waitForTimeout(100);
        await page.keyboard.type(' second');
        await expect(codeMirror).toContainText('first second');

        const isMac = process.platform === 'darwin';
        await page.keyboard.press(isMac ? 'Meta+z' : 'Control+z');

        // Block should still exist (Gutenberg undo would remove it)
        const block = page.locator('[data-type="blockstudio/type-code-undo"]');
        await expect(block).toBeVisible();
      },
    },
    {
      description: 'redo works within code field',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-code-undo"]');

        const codeMirror = page.locator('.blockstudio-fields__field--code .cm-content');
        await codeMirror.click();
        await page.keyboard.type('test');
        await page.waitForTimeout(100);

        const isMac = process.platform === 'darwin';
        await page.keyboard.press(isMac ? 'Meta+z' : 'Control+z');
        await page.keyboard.press(isMac ? 'Meta+Shift+z' : 'Control+y');

        const block = page.locator('[data-type="blockstudio/type-code-undo"]');
        await expect(block).toBeVisible();
      },
    },
  ];
});
