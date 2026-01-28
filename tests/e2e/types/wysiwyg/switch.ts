import { FrameLocator } from '@playwright/test';
import { click, count, press, testType } from '../../utils/playwright-utils';

testType('wysiwyg-switch', 'Default text', () => [
  {
    description: 'type and switch',
    testFunction: async (editor: FrameLocator) => {
      await click(editor, 'text=Switch to Code');
      await click(editor, '.cm-line');
      await press(editor, 'Meta+A');
      await press(editor, 'Backspace');
      await editor.locator('body').pressSequentially('<h1>Switch Test</h1>');
      await count(editor, 'text=Switch Test', 1);
      await click(editor, 'text=Switch to Visual');
      await count(editor, 'text=Switch Test', 2);
    },
  },
]);
