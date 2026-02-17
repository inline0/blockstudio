import { Frame, Page, expect } from '@playwright/test';
import {
  checkStyle,
  count,
  getEditorCanvas,
  save,
  saveAndReload,
  testType,
} from '../utils/playwright-utils';

testType(
  'text',
  false,
  () => {
    return [
      {
        description: 'change text',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-text"]');
          await page.fill('.blockstudio-fields__field--text input', '100');
          await saveAndReload(page);
        },
      },
      {
        description: 'check text',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-text"]');
          await expect(
            page.locator('.blockstudio-fields__field--text input').nth(0)
          ).toHaveValue('100');
        },
      },
      {
        description: 'add custom classes and attributes',
        testFunction: async (page: Page, canvas: Frame) => {
          await page.fill('[data-id="textClasses"] input', 'is-');
          await page.click('[data-id="textClasses"] *:has-text("is-large")');
          await page.click('text=Add Attribute');
          await page.fill('[placeholder="Attribute"]', 'data-test');
          await page.click('.cm-content');
          await page.keyboard.type('test');
          await page.fill('[data-id="textClass"] input', 'test');
          await page.fill('[data-id="textClass2"] input', 'test2');
          await count(canvas, '.text-test', 1);
          await count(canvas, '.text-test2-2', 1);
          for (const item of ['.blockstudio-test__block']) {
            await count(canvas, `${item}.is-large`, 1);
            await count(canvas, `${item}[data-test="test"]`, 1);
            await checkStyle(canvas, item, 'color', 'rgb(255, 0, 0)');
            await checkStyle(
              canvas,
              item,
              'background',
              'rgba(0, 0, 0, 0) linear-gradient(135deg, rgb(18, 194, 233) 0%, rgb(196, 113, 237) 50%, rgb(246, 79, 89) 100%) repeat scroll 0% 0% / auto padding-box border-box'
            );
          }
        },
      },
      {
        description: 'add heading',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-text"]');
          await page.keyboard.press('Enter');
          await page.keyboard.type('/heading', {
            delay: 100,
          });
          await page.keyboard.press('Enter');
          await page.keyboard.type('Heading test', {
            delay: 100,
          });
          await page.click('[data-id="allClasses"] input');
          await page.keyboard.type('is-', {
            delay: 100,
          });
          // await page.keyboard.press('ArrowDown');
          // await page.keyboard.press('ArrowDown');
          // await count(canvas, '.wp-block-heading.is-style-squared', 1);
          // await page.keyboard.press('Escape');
          // await count(canvas, '.wp-block-heading.is-style-squared', 0);
          await page.click('[data-id="allClasses"] *:has-text("is-large")');
          await page.click('text=Add Attribute');
          await page.fill('[placeholder="Attribute"]', 'data-test');
          await page.locator('.cm-line').nth(0).click();
          await page.keyboard.type('test');
          await page.fill('[data-id="allClass"] input', 'test');
          await page.fill('[data-id="allClass2"] input', 'test2');
          await page.fill('[data-id="allData"] input', 'test-attribute');
          await page.click('text=All class 3');
          await page.click('text=Option 1');
          await page.click('text=Option 2');
          await page.locator('.cm-line').nth(1).click();
          await page.keyboard.press('ControlOrMeta+A');
          await page.keyboard.press('Backspace');
          await page.keyboard.type(
            '%selector% { text-decoration: underline; }'
          );
          await count(canvas, '.select-class-2', 1);
          await count(canvas, '.select-post-native', 1);
          await count(canvas, '.select-post-Reusable', 1);
          await count(canvas, '.select-post-native-Native', 1);
          await count(canvas, '[data-test-attribute="test-attribute"]', 1);
          await count(canvas, '.all-test', 1);
          await count(canvas, '.all-test2-2', 1);
          await count(canvas, '.all-toggle', 1);
          await count(canvas, '.all-checkbox-option-1', 1);
          await count(canvas, '.all-checkbox-option-2', 1);
          for (const item of ['.wp-block-heading']) {
            await count(canvas, `${item}.is-large`, 1);
            await count(canvas, `${item}[data-test="test"]`, 1);
            await checkStyle(canvas, item, 'color', 'rgb(255, 0, 0)');
            await checkStyle(canvas, item, 'textDecorationLine', 'underline');
            await checkStyle(
              canvas,
              item,
              'background',
              'rgba(0, 0, 0, 0) linear-gradient(135deg, rgb(18, 194, 233) 0%, rgb(196, 113, 237) 50%, rgb(246, 79, 89) 100%) repeat scroll 0% 0% / auto padding-box border-box'
            );
          }
        },
      },
      {
        description: 'check front',
        testFunction: async (page: Page) => {
          await save(page);
          await page.goto('http://localhost:8888/native-single/');
          await count(page, '.text-test', 1);
          await count(page, '.text-test2-2', 1);
          await count(page, '.select-class-2', 1);
          await count(page, '.select-post-native', 1);
          await count(page, '.select-post-Reusable', 1);
          await count(page, '.select-post-native-Native', 1);
          await count(page, '[data-test-attribute="test-attribute"]', 1);
          await count(page, '.all-test', 1);
          await count(page, '.all-test2-2', 1);
          await count(page, '.all-toggle', 1);
          await count(page, '.all-checkbox-option-1', 1);
          await count(page, '.all-checkbox-option-2', 1);
          await checkStyle(page, '.wp-block-heading', 'textDecorationLine', 'underline');
          for (const item of [
            '.blockstudio-test__block',
            '.wp-block-heading',
          ]) {
            await count(page, `${item}.is-large`, 1);
            await count(page, `${item}[data-test="test"]`, 1);
            await checkStyle(page, item, 'color', 'rgb(255, 0, 0)');
            await checkStyle(
              page,
              item,
              'background',
              'rgba(0, 0, 0, 0) linear-gradient(135deg, rgb(18, 194, 233) 0%, rgb(196, 113, 237) 50%, rgb(246, 79, 89) 100%) repeat scroll 0% 0% / auto padding-box border-box'
            );
          }
          await page.goto(
            'http://localhost:8888/wp-admin/post.php?post=1483&action=edit'
          );
          const canvas = await getEditorCanvas(page);
          await count(canvas, '.editor-styles-wrapper', 1);
        },
      },
    ];
  }
);
