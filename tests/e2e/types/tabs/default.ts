import { FrameLocator } from '@playwright/test';
import {
  click,
  count,
  fill,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';

testType(
  'tabs',
  '"toggle":false,"toggle2":false,"text":false,"text2":false,"group_text":false,"group_text2":false',
  () => {
    return [
      {
        description: 'only two tabs',
        testFunction: async (editor: FrameLocator) => {
          await count(editor, 'text=Tab 1', 1);
          await count(editor, 'text=Override tab', 1);
          await count(editor, 'text=Tab 2', 0);
        },
      },
      {
        description: 'change first tab',
        testFunction: async (editor: FrameLocator) => {
          await editor
            .locator('.blockstudio-fields__field--text input')
            .nth(0)
            .fill('test');
          await editor
            .locator('.blockstudio-fields__field--text input')
            .nth(1)
            .fill('test');
          await text(
            editor,
            '"toggle":false,"toggle2":false,"text":"test","text2":"test","group_text":false,"group_text2":false'
          );
        },
      },
      {
        description: 'toggle',
        testFunction: async (editor: FrameLocator) => {
          await editor
            .locator('.blockstudio-fields__field--toggle input')
            .check();
          await text(
            editor,
            '"toggle":true,"toggle2":false,"text":"test","text2":"test","group_text":false,"group_text2":false'
          );
          await count(editor, 'text=Toggle 2', 1);
        },
      },
      {
        description: 'change second tab',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, 'text=Override tab');
          await editor
            .locator('.blockstudio-fields__field--text input')
            .nth(0)
            .fill('test');
          await editor
            .locator('.blockstudio-fields__field--text input')
            .nth(1)
            .fill('test');
          await text(
            editor,
            '"toggle":true,"toggle2":false,"text":"test","text2":"test","group_text":"test","group_text2":"test"'
          );
          await saveAndReload(editor);
        },
      },
      {
        description: 'check',
        testFunction: async (editor: FrameLocator) => {
          await text(
            editor,
            '"toggle":true,"toggle2":false,"text":"test","text2":"test","group_text":"test","group_text2":"test"'
          );
        },
      },
    ];
  }
);
