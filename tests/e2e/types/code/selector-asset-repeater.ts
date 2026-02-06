import { Page, Frame } from '@playwright/test';
import {
  checkStyle,
  getEditorCanvas,
  saveAndReload,
  testType,
} from '../../utils/playwright-utils';

testType('code-selector-asset-repeater', false, () => {
  return [
    {
      description: 'check and change code',
      testFunction: async (page: Page, canvas: Frame) => {
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await canvas.click(
          '[data-type="blockstudio/type-code-selector-asset-repeater"]'
        );
        await page.click('text=Add row');
        await page
          .locator('[data-id="codeTwo"]')
          .nth(1)
          .locator('.cm-line')
          .click();
        await page.keyboard.press('Meta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type('%selector% { border-radius: 12px; };');
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'border-radius',
          '12px'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check code',
      testFunction: async (_page: Page, canvas: Frame) => {
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          canvas,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'border-radius',
          '12px'
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
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          page,
          '.blockstudio-test__block',
          'border-radius',
          '12px'
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
