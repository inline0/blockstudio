import { Page } from '@playwright/test';
import { count, testType } from '../../../playwright-utils';

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
              testFunction: async (page: Page) => {
                if (item === 'repeater') {
                  await page.click(`text=Add row`);
                }

                await page
                  .locator(
                    `.blockstudio-fields__field--${item} .blockstudio-fields__field--toggle .components-form-toggle__input`
                  )
                  .first()
                  .click();
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Text on toggle"]`,
                  1
                );
              },
            },
            {
              description: 'text on includes value',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Text on toggle"] + div input`
                );
                await page.keyboard.press('t');
                await page.keyboard.press('e');
                await page.keyboard.press('s');
                await page.keyboard.press('t');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Text on includes value"]`,
                  1
                );
              },
            },
            {
              description: 'number on empty',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Text on includes value"] + div input`
                );
                await page.keyboard.press('t');
                await page.keyboard.press('e');
                await page.keyboard.press('s');
                await page.keyboard.press('t');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on empty"]`,
                  1
                );
              },
            },
            {
              description: 'number on smaller than',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Number on empty"] + div input`
                );
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"]`,
                  0
                );
                await page.keyboard.press('ArrowDown');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"]`,
                  1
                );
              },
            },
            {
              description: 'number on smaller than or even',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"] + div input`
                );
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="=Number on smaller than or even"]`,
                  0
                );
                await page.keyboard.press('ArrowDown');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than or even"]`,
                  1
                );
              },
            },
            {
              description: 'number on bigger than',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Number on smaller than or even"] + div input`
                );
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"]`,
                  0
                );
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"]`,
                  1
                );
              },
            },
            {
              description: 'number on bigger than or even',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"] + div input`
                );
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"]`,
                  0
                );
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"]`,
                  1
                );
              },
            },
            {
              description: 'select on bigger than or even',
              testFunction: async (page: Page) => {
                await page.click(
                  `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"] + div input`
                );
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Select on bigger than or even"]`,
                  0
                );
                await page.keyboard.press('ArrowUp');
                await count(
                  page,
                  `.blockstudio-fields__field--${item} [aria-label="Select on bigger than or even"]`,
                  1
                );
              },
            },
            {
              description: 'final toggle',
              testFunction: async (page: Page) => {
                await page.selectOption(
                  `.blockstudio-fields__field--${item} .blockstudio-fields__field--select select`,
                  {
                    value: 'test2',
                  }
                );
                await count(
                  page,
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
          testFunction: async (page: Page) => {
            await page.click(`text=Add main`);
            await count(page, `text=Text on toggle`, 3);
          },
        },
        {
          description: 'text on includes value',
          testFunction: async (page: Page) => {
            await count(page, `text=Text on includes value`, 3);
          },
        },
        {
          description: 'number on empty',
          testFunction: async (page: Page) => {
            await count(page, `text=Number on empty`, 3);
          },
        },
        {
          description: 'number on smaller than',
          testFunction: async (page: Page) => {
            await count(page, `text=Number on smaller than`, 6);
          },
        },
        {
          description: 'number on smaller than or even',
          testFunction: async (page: Page) => {
            await count(page, `text=Number on smaller than or even`, 3);
          },
        },
        {
          description: 'number on bigger than',
          testFunction: async (page: Page) => {
            await count(page, `text=Number on bigger than`, 6);
          },
        },
        {
          description: 'number on bigger than or even',
          testFunction: async (page: Page) => {
            await count(page, `text=Number on bigger than or even`, 3);
          },
        },
        {
          description: 'select on bigger than or even',
          testFunction: async (page: Page) => {
            await count(page, `text=Select on bigger than or even`, 3);
          },
        },
        {
          description: 'final toggle',
          testFunction: async (page: Page) => {
            await count(page, `text=Final toggle`, 3);
          },
        },
      ],
    },
  ];
});
