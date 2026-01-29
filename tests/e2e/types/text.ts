import { Page, expect } from '@playwright/test';
import {
  checkStyle,
  click,
  count,
  delay,
  fill,
  navigateToEditor,
  navigateToFrontend,
  press,
  save,
  saveAndReload,
  testType,
} from '../utils/playwright-utils';

testType(
  'text',
  '"text":"Default value","textClasses":false,"textAttributes":false,"textClassSelect":"class-2","textClassSelectPopulateValue":{"ID":1386,"post_author":"1","post_date":"2022-07-09 06:36:30","post_date_gmt":"2022-07-09 06:36:30","post_content":"","post_title":"Native","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native","to_ping":"","pinged":"","post_modified":"2023-06-17 13:57:45","post_modified_gmt":"2023-06-17 13:57:45","post_content_filtered":"","post_parent":0,"guid":"http:\\/\\/localhost:8888\\/?p=1386","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"textClassSelectPopulateLabel":false,"textClassSelectPopulateBoth":{"value":{"ID":1386,"post_author":"1","post_date":"2022-07-09 06:36:30","post_date_gmt":"2022-07-09 06:36:30","post_content":"","post_title":"Native","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native","to_ping":"","pinged":"","post_modified":"2023-06-17 13:57:45","post_modified_gmt":"2023-06-17 13:57:45","post_content_filtered":"","post_parent":0,"guid":"http:\\/\\/localhost:8888\\/?p=1386","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"label":"Native"},"textColor":{"name":"red","value":"#f00","slug":"red"},"textBackground":{"name":"JShine","value":"linear-gradient(135deg,#12c2e9 0%,#c471ed 50%,#f64f59 100%)","slug":"jshine"},"textClass":false,"textClass2":false',
  () => {
    return [
      {
        description: 'change text',
        testFunction: async (editor: Page) => {
          await click(editor, '[data-type="blockstudio/type-text"]');
          await fill(editor, '[data-id="text"] input', '100');
          await saveAndReload(editor);
        },
      },
      {
        description: 'check text',
        testFunction: async (editor: Page) => {
          await click(editor, '[data-type="blockstudio/type-text"]');
          await expect(
            editor.locator('[data-id="text"] input')
          ).toHaveValue('100');
        },
      },
      {
        description: 'add custom classes and attributes',
        testFunction: async (editor: Page) => {
          await fill(editor, '[data-id="textClasses"] input', 'is-');
          await click(editor, '[data-id="textClasses"] [role="option"]:has-text("is-large")');
          await click(editor, 'text=Add Attribute');
          await fill(editor, '[placeholder="Attribute"]', 'data-test');
          await click(editor, '.cm-content');
          await editor.locator('body').pressSequentially('test');
          await fill(editor, '[data-id="textClass"] input', 'test');
          await fill(editor, '[data-id="textClass2"] input', 'test2');
          await count(editor, '.text-test', 1);
          await count(editor, '.text-test2-2', 1);
          for (const item of ['.blockstudio-test__block']) {
            await count(editor, `${item}.is-large`, 1);
            await count(editor, `${item}[data-test="test"]`, 1);
            await checkStyle(editor, item, 'color', 'rgb(255, 0, 0)');
            await checkStyle(
              editor,
              item,
              'background',
              'rgba(0, 0, 0, 0) linear-gradient(135deg, rgb(18, 194, 233) 0%, rgb(196, 113, 237) 50%, rgb(246, 79, 89) 100%) repeat scroll 0% 0% / auto padding-box border-box'
            );
          }
        },
      },
      {
        description: 'add heading',
        testFunction: async (editor: Page) => {
          await click(editor, '[data-type="blockstudio/type-text"]');
          await press(editor, 'Enter');
          await editor.locator('body').pressSequentially('/heading', {
            delay: 100,
          });
          await press(editor, 'Enter');
          await editor.locator('body').pressSequentially('Heading test', {
            delay: 100,
          });
          await click(editor, '[data-id="allClasses"] input');
          await editor.locator('body').pressSequentially('is-', {
            delay: 100,
          });
          // await press(editor, 'ArrowDown');
          // await press(editor, 'ArrowDown');
          // await count(editor, '.wp-block-heading.is-style-squared', 1);
          // await press(editor, 'Escape');
          // await count(editor, '.wp-block-heading.is-style-squared', 0);
          await click(editor, '[data-id="allClasses"] [role="option"]:has-text("is-large")');
          await click(editor, 'text=Add Attribute');
          await fill(editor, '[placeholder="Attribute"]', 'data-test');
          await editor.locator('.cm-line').nth(0).click();
          await editor.locator('body').pressSequentially('test');
          await fill(editor, '[data-id="allClass"] input', 'test');
          await fill(editor, '[data-id="allClass2"] input', 'test2');
          await fill(editor, '[data-id="allData"] input', 'test-attribute');
          await click(editor, 'text=All class 3');
          await click(editor, 'text=Option 1');
          await click(editor, 'text=Option 2');
          await editor.locator('.cm-line').nth(1).click();
          await press(editor, 'Meta+A');
          await press(editor, 'Backspace');
          await editor.locator('body').pressSequentially(
            '%selector% { text-decoration: underline; }'
          );
          await count(editor, '.select-class-2', 1);
          await count(editor, '.select-post-native', 1);
          await count(editor, '.select-post-Reusable', 1);
          await count(editor, '.select-post-native-Native', 1);
          await count(editor, '[data-test-attribute="test-attribute"]', 1);
          await count(editor, '.all-test', 1);
          await count(editor, '.all-test2-2', 1);
          await count(editor, '.all-toggle', 1);
          await count(editor, '.all-checkbox-option-1', 1);
          await count(editor, '.all-checkbox-option-2', 1);
          for (const item of ['.wp-block-heading']) {
            await count(editor, `${item}.is-large`, 1);
            await count(editor, `${item}[data-test="test"]`, 1);
            await checkStyle(editor, item, 'color', 'rgb(255, 0, 0)');
            await checkStyle(
              editor,
              item,
              'textDecoration',
              'underline solid rgb(255, 0, 0)'
            );
            await checkStyle(
              editor,
              item,
              'background',
              'rgba(0, 0, 0, 0) linear-gradient(135deg, rgb(18, 194, 233) 0%, rgb(196, 113, 237) 50%, rgb(246, 79, 89) 100%) repeat scroll 0% 0% / auto padding-box border-box'
            );
          }
        },
      },
      {
        description: 'check front',
        testFunction: async (editor: Page) => {
          await save(editor);
          await delay(2000);
          // Navigate to frontend
          await navigateToFrontend(editor);
          await count(editor, '.text-test', 1);
          await count(editor, '.text-test2-2', 1);
          await count(editor, '.select-class-2', 1);
          await count(editor, '.select-post-native', 1);
          await count(editor, '.select-post-Reusable', 1);
          await count(editor, '.select-post-native-Native', 1);
          await count(editor, '[data-test-attribute="test-attribute"]', 1);
          await count(editor, '.all-test', 1);
          await count(editor, '.all-test2-2', 1);
          await count(editor, '.all-toggle', 1);
          await count(editor, '.all-checkbox-option-1', 1);
          await count(editor, '.all-checkbox-option-2', 1);
          await checkStyle(
            editor,
            '.wp-block-heading',
            'textDecoration',
            'underline solid rgb(255, 0, 0)'
          );
          for (const item of [
            '.blockstudio-test__block',
            '.wp-block-heading',
          ]) {
            await count(editor, `${item}.is-large`, 1);
            await count(editor, `${item}[data-test="test"]`, 1);
            await checkStyle(editor, item, 'color', 'rgb(255, 0, 0)');
            await checkStyle(
              editor,
              item,
              'background',
              'rgba(0, 0, 0, 0) linear-gradient(135deg, rgb(18, 194, 233) 0%, rgb(196, 113, 237) 50%, rgb(246, 79, 89) 100%) repeat scroll 0% 0% / auto padding-box border-box'
            );
          }
          // Navigate back to editor via admin bar
          await navigateToEditor(editor);
        },
      },
    ];
  }
);
