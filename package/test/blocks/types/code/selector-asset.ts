import { Page } from '@playwright/test';
import {
  checkStyle,
  count,
  saveAndReload,
  testType,
} from '../../../../playwright-utils';

testType('code-selector-asset', false, () => {
  return [
    {
      description: 'check and change code',
      testFunction: async (page: Page) => {
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await page.click('[data-type="blockstudio/type-code-selector-asset"]');
        await page.click('.cm-line');
        await page.keyboard.press('Meta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type(
          '%selector% { background: black; } %selector% h1 { color: yellow !important; }'
        );
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset"] h1',
          'color',
          'rgb(255, 255, 0)'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check code',
      testFunction: async (page: Page) => {
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset"]',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          page,
          '[data-type="blockstudio/type-code-selector-asset"] h1',
          'color',
          'rgb(255, 255, 0)'
        );
      },
    },
    {
      description: 'check frontend',
      testFunction: async (page: Page) => {
        await page.goto('https://fabrikat.local/blockstudio/native-single');
        await checkStyle(
          page,
          '.blockstudio-test__block',
          'background',
          'rgb(0, 0, 0) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await checkStyle(
          page,
          '.blockstudio-test__block h1',
          'color',
          'rgb(255, 255, 0)'
        );
        await page.goto(
          `https://fabrikat.local/blockstudio/wp-admin/post.php?post=1483&action=edit`
        );
        await page.reload();
        await count(page, '.editor-styles-wrapper', 1);
      },
    },
  ];
});
