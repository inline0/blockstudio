import { Page } from '@playwright/test';
import {
  checkStyle,
  count,
  saveAndReload,
  testType,
} from '../../utils/playwright-utils';

testType('code-selector-asset-repeater', false, () => {
  return [
    {
      description: 'check and change code',
      testFunction: async (page: Page) => {
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await page.click(
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
          page,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'border-radius',
          '12px'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check code',
      testFunction: async (page: Page) => {
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset-repeater"]',
          'border-radius',
          '12px'
        );
      },
    },
    {
      description: 'check frontend',
      testFunction: async (page: Page) => {
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
        await count(page, '.editor-styles-wrapper', 1);
      },
    },
  ];
});
