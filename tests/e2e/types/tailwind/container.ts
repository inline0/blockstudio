import { Page } from '@playwright/test';
import {
  addBlock,
  addTailwindClass,
  checkStyle,
  click,
  count,
  delay,
  fill,
  navigateToEditor,
  navigateToFrontend,
  press,
  removeBlocks,
  save,
  testType,
} from '../../utils/playwright-utils';

testType('tailwind', false, () => {
  return [
    {
      description: 'add container',
      testFunction: async (editor: Page) => {
        await removeBlocks(editor);
        await addBlock(editor, 'container');
        await click(
          editor,
          '[data-type="blockstudio/container"] .block-editor-inserter__toggle'
        );
        await count(editor, '.components-popover__content', 2);
        await editor.locator('[placeholder="Search"]').first().fill('heading');
        await editor.locator('.editor-block-list-item-heading').waitFor({ state: 'visible' });
        await editor.locator('.editor-block-list-item-heading').click();
      },
    },
    {
      description: 'add attributes',
      testFunction: async (editor: Page) => {
        await addTailwindClass(editor, 'Container', 'text-blue-950');
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
          .locator('.blockstudio-builder__controls [aria-label="More"]')
          .nth(2)
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
          .locator('.blockstudio-builder__controls [aria-label="More"]')
          .nth(3)
          .click();
        await click(editor, 'text=Insert Media');
        await click(editor, 'text=Media Library');
        await click(editor, '[data-id="1604"]');
        await click(editor, '.media-button-select');
        await count(editor, '.blockstudio-fields__field--files-toggle', 1);

        await count(editor, '[data-test="test"]', 1);
        await count(editor, '[data-link="https://google.com"]', 1);
      },
    },
    {
      description: 'test classes',
      testFunction: async (editor: Page) => {
        await addTailwindClass(editor, 'Container', 'bg-red-50');
        await click(editor, '.blockstudio-builder__controls [role="combobox"]');
        await fill(
          editor,
          '.blockstudio-builder__controls [role="combobox"]',
          'bg-red'
        );
        // await press(editor, 'ArrowDown');
        // await count(editor, '.\\!bg-red-100', 1);
        // await press(editor, 'Escape');
        // await count(editor, '.\\!bg-red-100', 0);
      },
    },
    {
      description: 'add classes',
      testFunction: async (editor: Page) => {
        await addTailwindClass(editor, 'Container', 'p-4');
        await addTailwindClass(editor, 'Container', 'bg-red-500');
        await count(editor, '.p-4', 1);
        await count(editor, '.bg-red-500', 1);
        await addTailwindClass(editor, 'Container', 'bg-red-600');
        await count(editor, '.bg-red-600', 1);
        await count(editor, '.bg-red-500', 0);
      },
    },
    {
      description: 'check frontend',
      testFunction: async (editor: Page) => {
        await save(editor);
        await delay(2000);
        // Navigate to frontend
        await navigateToFrontend(editor);
        await count(editor, '.bg-red-600', 1);
        await checkStyle(
          editor,
          '.bg-red-600',
          'background',
          'rgb(220, 38, 38) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await count(editor, '.p-4', 1);
        await checkStyle(editor, '.p-4', 'padding', '16px');
        await count(editor, '[data-test="test"]', 1);
        await count(editor, '[data-link="https://google.com"]', 1);
        await count(editor, '[data-image*="https://"]', 1);
        // Navigate back to editor via admin bar
        await navigateToEditor(editor);
      },
    },
  ];
});
