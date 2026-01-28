import { FrameLocator } from '@playwright/test';
import { count, fill, testType, text } from '../utils/playwright-utils';

testType(
  'group',
  '"text":"Override test","toggle":false,"toggle2":false,"group_text":"Override ID test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"',
  () => {
    return [
      {
        description: 'without id',
        testFunction: async (editor: FrameLocator) => {
          await fill(
            editor,
            '.blockstudio-fields__field.blockstudio-fields__field--group input[type="text"]',
            'test'
          );
          await text(
            editor,
            '"text":"test","toggle":false,"toggle2":false,"group_text":"Override ID test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
        },
      },
      {
        description: 'condition without id',
        testFunction: async (editor: FrameLocator) => {
          await editor
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="checkbox"]'
            )
            .check();
          await text(
            editor,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"Override ID test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
          await count(editor, 'text=Toggle 2', 1);
        },
      },
      {
        description: 'with id',
        testFunction: async (editor: FrameLocator) => {
          await editor
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="text"]'
            )
            .nth(1)
            .fill('test');
          await text(
            editor,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
        },
      },
      {
        description: 'condition with id',
        testFunction: async (editor: FrameLocator) => {
          await editor
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="checkbox"]'
            )
            .nth(2)
            .check();
          await text(
            editor,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"test","group_toggle3":true,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
          await count(editor, 'text=Toggle 4', 1);
        },
      },
      {
        description: 'added element from override',
        testFunction: async (editor: FrameLocator) => {
          await editor
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="text"]'
            )
            .nth(4)
            .fill('test');
          await text(
            editor,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"test","group_toggle3":true,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"test"'
          );
        },
      },
      {
        description: 'with style',
        testFunction: async (editor: FrameLocator) => {
          await text(
            editor,
            'style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;"'
          );
        },
      },
      {
        description: 'with disabled toggles',
        testFunction: async (editor: FrameLocator) => {
          await count(
            editor,
            '.blockstudio-fields__field--group >> nth=2 .blockstudio-fields__field-toggle',
            0
          );
        },
      },
    ];
  }
);
