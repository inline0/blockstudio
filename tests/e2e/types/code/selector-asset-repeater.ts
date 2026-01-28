import { FrameLocator } from '@playwright/test';
import {
  checkStyle,
  click,
  navigateToEditor,
  navigateToFrontend,
  press,
  save,
  saveAndReload,
  testType,
} from '../../utils/playwright-utils';

testType('code-selector-asset-repeater', false, () => {
  return [
    {
      description: 'check and change code',
      testFunction: async (editor: FrameLocator) => {
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await click(
          editor,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]'
        );
        await click(editor, 'text=Add row');
        await editor
          .locator('[data-id="codeTwo"]')
          .nth(1)
          .locator('.cm-line')
          .click();
        await press(editor, 'Meta+A');
        await press(editor, 'Backspace');
        await editor.locator('body').pressSequentially('%selector% { border-radius: 12px; };');
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'border-radius',
          '12px'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check code',
      testFunction: async (editor: FrameLocator) => {
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'border-radius',
          '12px'
        );
      },
    },
    {
      description: 'check frontend',
      testFunction: async (editor: FrameLocator) => {
        // Save and navigate to frontend
        await save(editor);
        await navigateToFrontend(editor);
        await checkStyle(
          editor,
          '.blockstudio-test__block',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          editor,
          '.blockstudio-test__block',
          'border-radius',
          '12px'
        );
        // Navigate back to editor via admin bar
        await navigateToEditor(editor);
      },
    },
  ];
});
