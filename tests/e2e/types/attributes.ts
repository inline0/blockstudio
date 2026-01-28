import { FrameLocator } from '@playwright/test';
import { click, count, delay, fill, navigateToEditor, navigateToFrontend, press, save, testType } from '../utils/playwright-utils';

testType('attributes', false, () => {
  return [
    {
      description: 'add attributes',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, 'text=Add Attribute');
        await editor
          .locator('[placeholder="Attribute"]')
          .nth(0)
          .fill('data-test');
        await editor.locator('.cm-activeLine.cm-line').nth(0).click();
        await press(editor, 'Meta+A');
        await press(editor, 'Backspace');
        await editor.locator('body').pressSequentially('test');

        await click(editor, 'text=Add Attribute');
        await editor
          .locator('[placeholder="Attribute"]')
          .nth(1)
          .fill('data-link');
        await editor.locator('.cm-activeLine.cm-line').nth(1).click();
        await editor
          .locator('.blockstudio-fields [aria-label="More"]')
          .nth(1)
          .click();
        await click(editor, 'text=Insert Link');
        await fill(
          editor,
          '[placeholder="Search or type URL"]',
          'https://google.com'
        );
        await press(editor, 'Enter');
        await click(editor, '.components-modal__header [aria-label="Close"]');

        await click(editor, 'text=Add Attribute');
        await editor
          .locator('[placeholder="Attribute"]')
          .nth(2)
          .fill('data-image');
        await editor.locator('.cm-activeLine.cm-line').nth(1).click();
        await editor
          .locator('.blockstudio-fields [aria-label="More"]')
          .nth(2)
          .click();
        await delay(1000);
        await click(editor, 'text=Insert Media');
        await delay(1000);
        await click(editor, 'text=Media Library');
        await click(editor, '[data-id="1604"]');
        await click(editor, '.media-button-select');
        await count(editor, '.blockstudio-fields__field--files-toggle', 1);

        await count(editor, '[data-test="test"]', 1);
        await count(editor, '[data-link="https://google.com"]', 1);
      },
    },
    {
      description: 'check frontend',
      testFunction: async (editor: FrameLocator) => {
        await save(editor);
        await delay(2000);
        // Navigate to frontend
        await navigateToFrontend(editor);
        await count(editor, '[data-test="test"]', 1);
        await count(editor, '[data-link="https://google.com"]', 1);
        await count(editor, '[data-image*="https://"]', 1);
        // Navigate back to editor via admin bar
        await navigateToEditor(editor);
      },
    },
  ];
});
