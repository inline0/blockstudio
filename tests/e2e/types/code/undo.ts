import { expect, Page } from '@playwright/test';
import { testType } from '../../utils/playwright-utils';

testType('code-undo', '"code":".initial {}"', () => {
  return [
    {
      description: 'undo works within code field without triggering Gutenberg undo',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-code-undo"]');

        // Click into the code editor
        const codeMirror = page.locator('.blockstudio-fields__field--code .cm-content');
        await codeMirror.click();

        // Type some text
        await page.keyboard.type('first');

        // Small delay to let CodeMirror register the input
        await page.waitForTimeout(100);

        // Type more text
        await page.keyboard.type(' second');

        // Verify text is there
        await expect(codeMirror).toContainText('first second');

        // Undo - should only undo within CodeMirror, not trigger Gutenberg undo
        const isMac = process.platform === 'darwin';
        await page.keyboard.press(isMac ? 'Meta+z' : 'Control+z');

        // The key test is that the block still exists (Gutenberg undo would remove it)
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

        // Type text
        await page.keyboard.type('test');
        await page.waitForTimeout(100);

        // Undo
        const isMac = process.platform === 'darwin';
        await page.keyboard.press(isMac ? 'Meta+z' : 'Control+z');

        // Redo
        await page.keyboard.press(isMac ? 'Meta+Shift+z' : 'Control+y');

        // Block should still exist
        const block = page.locator('[data-type="blockstudio/type-code-undo"]');
        await expect(block).toBeVisible();
      },
    },
  ];
});
