import { FrameLocator } from '@playwright/test';
import { click, count, press, testType } from '../utils/playwright-utils';

testType('conditions', false, () => {
  return [
    {
      params: ['group', 'repeater'],
      generateTestCases: (item) => [
        {
          groupName: `${item}`,
          testCases: [
            {
              description: 'text on toggle',
              testFunction: async (editor: FrameLocator) => {
                if (item === 'repeater') {
                  await click(editor, `text=Add row`);
                }

                await editor
                  .locator(
                    `.blockstudio-fields__field--${item} .blockstudio-fields__field--toggle .components-form-toggle__input`
                  )
                  .first()
                  .click();
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Text on toggle"]`,
                  1
                );
              },
            },
            {
              description: 'text on includes value',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Text on toggle"] + div input`
                );
                await press(editor, 't');
                await press(editor, 'e');
                await press(editor, 's');
                await press(editor, 't');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Text on includes value"]`,
                  1
                );
              },
            },
            {
              description: 'number on empty',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Text on includes value"] + div input`
                );
                await press(editor, 't');
                await press(editor, 'e');
                await press(editor, 's');
                await press(editor, 't');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on empty"]`,
                  1
                );
              },
            },
            {
              description: 'number on smaller than',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on empty"] + div input`
                );
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"]`,
                  0
                );
                await press(editor, 'ArrowDown');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"]`,
                  1
                );
              },
            },
            {
              description: 'number on smaller than or even',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"] + div input`
                );
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="=Number on smaller than or even"]`,
                  0
                );
                await press(editor, 'ArrowDown');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than or even"]`,
                  1
                );
              },
            },
            {
              description: 'number on bigger than',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than or even"] + div input`
                );
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"]`,
                  0
                );
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"]`,
                  1
                );
              },
            },
            {
              description: 'number on bigger than or even',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"] + div input`
                );
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"]`,
                  0
                );
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"]`,
                  1
                );
              },
            },
            {
              description: 'select on bigger than or even',
              testFunction: async (editor: FrameLocator) => {
                await click(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"] + div input`
                );
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Select on bigger than or even"]`,
                  0
                );
                await press(editor, 'ArrowUp');
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} [aria-label="Select on bigger than or even"]`,
                  1
                );
              },
            },
            {
              description: 'final toggle',
              testFunction: async (editor: FrameLocator) => {
                await editor
                  .locator(
                    `.blockstudio-fields__field--${item} .blockstudio-fields__field--select select`
                  )
                  .selectOption({
                    value: 'test2',
                  });
                await count(
                  editor,
                  `.blockstudio-fields__field--${item} >> text=Final toggle`,
                  1
                );
              },
            },
          ],
        },
      ],
    },
    {
      groupName: 'operators repeater main',
      testCases: [
        {
          description: 'text on toggle',
          testFunction: async (editor: FrameLocator) => {
            await click(editor, `text=Add main`);
            await count(editor, `text=Text on toggle`, 3);
          },
        },
        {
          description: 'text on includes value',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Text on includes value`, 3);
          },
        },
        {
          description: 'number on empty',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Number on empty`, 3);
          },
        },
        {
          description: 'number on smaller than',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Number on smaller than`, 6);
          },
        },
        {
          description: 'number on smaller than or even',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Number on smaller than or even`, 3);
          },
        },
        {
          description: 'number on bigger than',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Number on bigger than`, 6);
          },
        },
        {
          description: 'number on bigger than or even',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Number on bigger than or even`, 3);
          },
        },
        {
          description: 'select on bigger than or even',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Select on bigger than or even`, 3);
          },
        },
        {
          description: 'final toggle',
          testFunction: async (editor: FrameLocator) => {
            await count(editor, `text=Final toggle`, 3);
          },
        },
      ],
    },
  ];
});
