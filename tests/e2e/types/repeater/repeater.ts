import { FrameLocator } from '@playwright/test';
import { click, count, press, testType, text } from '../../utils/playwright-utils';

testType('repeater', false, () => {
  return [
    {
      description: 'correct minimized value',
      testFunction: async (editor: FrameLocator) => {
        await editor.locator('body').evaluate(() => window.localStorage.clear());

        await click(
          editor,
          '[aria-label="Repeater Minimized"] + div .blockstudio-repeater__minimize'
        );
        await count(editor, 'text=Prefix: 20 - Suffix', 1);
        await click(
          editor,
          '[aria-label="Repeater Minimized"] + div .blockstudio-repeater__minimize'
        );
      },
    },
    {
      description: 'correct min',
      testFunction: async (editor: FrameLocator) => {
        await count(editor, '.components-range-control', 2);
      },
    },
    {
      description: 'correct max',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, 'text=Add repeater 3');
        await count(editor, '.components-range-control', 3);
        await count(editor, '.is-secondary[disabled]', 1);
      },
    },
    {
      description: 'correct min on add',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, 'text=Add repeater 1');
        await count(editor, '.components-range-control', 5);
      },
    },
    {
      description: 'remove',
      testFunction: async (editor: FrameLocator) => {
        // Note: dialog handling requires page context, skipping confirmation in Playground
        await click(
          editor,
          '[data-rfd-draggable-id="repeater[0].repeater[0].repeater[2]"] > div > .blockstudio-repeater__remove'
        );
        await count(
          editor,
          '[data-rfd-draggable-id="repeater[0].repeater[0].repeater[0]"] > div > [role="button"]',
          2
        );
      },
    },
    {
      description: 'duplicate',
      testFunction: async (editor: FrameLocator) => {
        await click(
          editor,
          '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__duplicate'
        );
        await count(editor, '[data-rfd-draggable-id="repeater[1]"]', 1);
        await click(
          editor,
          '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__minimize'
        );
        await click(
          editor,
          '[data-rfd-draggable-id="repeater[1]"] .blockstudio-repeater__minimize'
        );
        await count(editor, 'text=Repeater element', 3);
      },
    },
    {
      description: 'reorder',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[aria-label="blue"]:visible');
        await editor.locator('[data-rfd-draggable-id="repeater[0]"]').focus();
        await press(editor, 'ArrowDown');
        await press(editor, 'ArrowDown');
        await text(
          editor,
          '"text":"Override test","repeater":[{"files":false,"defaultValueLabel":"Three","color":{"name":"red","value":"#f00","slug":"red"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]},{"files":false,"defaultValueLabel":"Three","color":{"value":"#00f","name":"blue","slug":"blue"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]},{"files":false,"defaultValueLabel":"Three","color":{"name":"red","value":"#f00","slug":"red"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]}]'
        );
      },
    },
  ];
});
