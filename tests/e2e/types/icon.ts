import { FrameLocator } from '@playwright/test';
import {
  click,
  count,
  expect,
  locator,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType(
  'icon',
  '"icon":{"set":"octicons","icon":"alert-16","element":',
  () => {
    return [
      {
        description: 'two svg',
        testFunction: async (editor: FrameLocator) => {
          await count(editor, '#blockstudio-type-icon svg', 2);
        },
      },
      {
        description: 'change icon',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, '[data-type="blockstudio/type-icon"]');
          await editor
            .locator(
              '.blockstudio-fields__field--icon .components-combobox-control__input'
            )
            .last()
            .click();
          await editor
            .locator(
              '.blockstudio-fields__field--icon .components-form-token-field__suggestion'
            )
            .nth(0)
            .click();
          await saveAndReload(editor);
        },
      },
      {
        description: 'check icon',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, '[data-type="blockstudio/type-icon"]');
          await text(editor, 'accessibility-16');
        },
      },
      {
        description: 'check repeater',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, 'text=Add an Icon');
          await click(editor, 'text=Add an Icon');
          await editor
            .locator('.blockstudio-fields .components-combobox-control__input')
            .nth(3)
            .click();
          await editor
            .locator(
              '.blockstudio-fields .components-form-token-field__suggestion'
            )
            .nth(0)
            .click();
          await editor.locator('.blockstudio-repeater__remove').nth(1).click();
          await expect(
            locator(editor, '.components-form-token-field__input').nth(2)
          ).not.toHaveValue('0');
        },
      },
    ];
  }
);
