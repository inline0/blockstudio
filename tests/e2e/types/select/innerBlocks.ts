import { Page } from '@playwright/test';
import { count, testType, text } from '../../utils/playwright-utils';

testType('select-innerblocks', '"layout":false', () => {
  return [
    {
      description: 'layout 1',
      testFunction: async (page: Page) => {
        await page.click('text=ID: blockstudio-type-select-innerblocks');
        await page.selectOption(
          '.blockstudio-fields .components-select-control__input',
          {
            value: '1',
          }
        );
        await count(page, 'text=This is the left Heading Level 1.', 1);
        await count(page, 'text=This is the right Heading Level 1.', 1);
        await count(page, 'text=This is the left Paragraph Level 1.', 1);
        await count(page, 'text=This is the right Paragraph Level 1.', 1);
        await text(
          page,
          '"text":"This is the left Text.","textClassSelect":"class-1"'
        );
        await page.click('text=This is the left Heading Level 1.');
        await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
        await page.click('text=This is the right Heading Level 1.');
        await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
      },
    },
    {
      description: 'layout 2',
      testFunction: async (page: Page) => {
        await page.click('text=ID: blockstudio-type-select-innerblocks');
        await page.selectOption(
          '.blockstudio-fields .components-select-control__input',
          {
            value: '2',
          }
        );
        await count(page, 'text=This is the left Heading Level 2.', 1);
        await count(page, 'text=This is the right Heading Level 2.', 1);
        await count(page, 'text=This is the left Paragraph Level 1.', 1);
        await count(page, 'text=This is the right Paragraph Level 1.', 1);
        await text(
          page,
          '"text":"This is the left Text.","textClassSelect":"class-1"'
        );
        await page.click('text=This is the left Heading Level 2.');
        await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
        await page.click('text=This is the right Heading Level 2.');
        await count(page, '[aria-label="blue"][aria-selected="true"]', 1);
      },
    },
  ];
});
