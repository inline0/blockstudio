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

testType('code-selector-asset', false, () => {
  return [
    {
      description: 'check and change code',
      testFunction: async (editor: FrameLocator) => {
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await click(editor, '[data-type="blockstudio/type-code-selector-asset"]');
        await click(editor, '.cm-line');
        await press(editor, 'Meta+A');
        await press(editor, 'Backspace');
        await editor.locator('body').pressSequentially(
          '%selector% { background: black; } %selector% h1 { color: yellow !important; }'
        );
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset"] h1',
          'color',
          'rgb(255, 255, 0)'
        );
        await saveAndReload(editor);
      },
    },
    {
      description: 'check code',
      testFunction: async (editor: FrameLocator) => {
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          editor,
          '[data-type="blockstudio/type-code-selector-asset"] h1',
          'color',
          'rgb(255, 255, 0)'
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
          '.blockstudio-test__block h1',
          'color',
          'rgb(255, 255, 0)'
        );
        // Navigate back to editor via admin bar
        await navigateToEditor(editor);
      },
    },
  ];
});
