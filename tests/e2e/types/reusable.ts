import { Page, expect } from '@playwright/test';
import {
  click,
  openBlockInserter,
  removeBlocks,
  testType,
} from '../utils/playwright-utils';

testType(
  'text',
  false,
  () => {
    return [
      {
        description: 'load multiple reusables',
        testFunction: async (editor: Page) => {
          await removeBlocks(editor);

          // Insert first pattern (Test Pattern 2)
          await openBlockInserter(editor);
          await click(editor, '[role="tab"]:has-text("Patterns")');
          await editor.locator('[placeholder="Search"]').first().fill('Test Pattern');
          await editor.locator('[role="option"]:has-text("Test Pattern 2")').click();
          // Wait for pattern to be inserted - toolbar shows the pattern name when selected
          await editor.locator('[aria-label="Block tools"] button:has-text("Test Pattern 2")').waitFor({ state: 'visible', timeout: 10000 });

          // Insert second pattern (Test Pattern 1)
          await openBlockInserter(editor);
          await click(editor, '[role="tab"]:has-text("Patterns")');
          await editor.locator('[placeholder="Search"]').first().fill('Test Pattern');
          await editor.locator('[role="option"]:has-text("Test Pattern 1")').click();
          // Wait for second pattern - toolbar now shows Test Pattern 1
          await editor.locator('[aria-label="Block tools"] button:has-text("Test Pattern 1")').waitFor({ state: 'visible', timeout: 10000 });

          // Close the inserter and wait for it to be fully hidden
          const closeButton = editor.locator('button[aria-label="Close block inserter"]');
          if ((await closeButton.count()) > 0) {
            await closeButton.click();
            // Wait for Block Library region to be hidden
            await editor.locator('[aria-label="Block Library"]').waitFor({ state: 'hidden', timeout: 5000 });
          }

          // Wait for editor to stabilize
          await editor.waitForTimeout(500);

          // Verify patterns were loaded - check that both Pattern buttons appear in breadcrumb/toolbar
          // The toolbar shows the selected pattern name, breadcrumb shows it in navigation
          const patternElements = editor.locator('button:has-text("Test Pattern"), [role="document"][aria-label*="Block: Pattern"]');
          await expect(patternElements.first()).toBeVisible();

          // Verify at least 2 Pattern blocks exist (counting document role elements)
          const patternBlocks = editor.locator('[role="document"][aria-label*="Block: Pattern"]');
          await expect(patternBlocks).toHaveCount(2);
        },
      },
    ];
  },
  false
);
