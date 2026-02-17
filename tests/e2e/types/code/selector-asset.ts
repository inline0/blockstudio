import { Page, Frame } from '@playwright/test';
import {
  checkStyle,
  getEditorCanvas,
  saveAndReload,
  testType,
} from '../../utils/playwright-utils';

testType('code-selector-asset', false, () => {
  return [
    {
      description: 'check and change code',
      testFunction: async (page: Page, canvas: Frame) => {
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'backgroundColor',
          'rgb(0, 0, 0)'
        );
        await canvas.click('[data-type="blockstudio/type-code-selector-asset"]');
        await page.click('.cm-line');
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type(
          '%selector% { background: black; } %selector% h1 { color: yellow !important; }'
        );
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'backgroundColor',
          'rgb(0, 0, 0)'
        );
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset"] h1',
          'color',
          'rgb(255, 255, 0)'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check code',
      testFunction: async (_page: Page, canvas: Frame) => {
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'backgroundColor',
          'rgb(0, 0, 0)'
        );
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset"] h1',
          'color',
          'rgb(255, 255, 0)'
        );
      },
    },
    {
      description: 'check frontend',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.goto('http://localhost:8888/native-single');
        await checkStyle(
          page,
          '.blockstudio-test__block',
          'backgroundColor',
          'rgb(0, 0, 0)'
        );
        await checkStyle(
          page,
          '.blockstudio-test__block h1',
          'color',
          'rgb(255, 255, 0)'
        );
        await page.goto(
          `http://localhost:8888/wp-admin/post.php?post=1483&action=edit`
        );
        await page.reload();
        await getEditorCanvas(page);
      },
    },
  ];
});
