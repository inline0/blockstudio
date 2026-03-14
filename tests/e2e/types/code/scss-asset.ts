import { Page, Frame } from '@playwright/test';
import {
  checkStyle,
  getEditorCanvas,
  saveAndReload,
  testType,
} from '../../utils/playwright-utils';

testType('code-scss-asset', false, () => {
  return [
    {
      description: 'change scss value',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click(
          '[data-type="blockstudio/type-code-scss-asset"]',
        );
        await page.click('.cm-line');
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type(
          '%selector% { $bg: blue; background: $bg; }',
        );
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-scss-asset"]',
          'backgroundColor',
          'rgb(0, 0, 255)',
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check scss compiled on frontend',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.goto('http://localhost:8888/native-single');
        await checkStyle(
          page,
          '#blockstudio-type-code-scss-asset',
          'backgroundColor',
          'rgb(0, 0, 255)',
        );
        await page.goto(
          'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
        );
        await page.reload();
        await getEditorCanvas(page);
      },
    },
  ];
});
