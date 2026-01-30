import { Page } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('radio-innerblocks', '"layout":false', () => {
  return [
    {
      description: 'layout 1',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-radio-innerblocks"]');
        await page
          .locator('.blockstudio-fields .components-radio-control__input')
          .nth(0)
          .click();
        await count(page, 'text=This is the left Heading Level 1.', 1);
        await count(page, 'text=This is the right Heading Level 1.', 1);
        await count(page, 'text=This is the left Paragraph Level 1.', 1);
        await count(page, 'text=This is the right Paragraph Level 1.', 1);
      },
    },
    {
      description: 'layout 2',
      testFunction: async (page: Page) => {
        await page.locator('.block-editor-block-breadcrumb button:has-text("Native Radio InnerBlocks")').click();
        await page
          .locator('.blockstudio-fields .components-radio-control__input')
          .nth(1)
          .click();
        await count(page, 'text=This is the left Heading Level 2.', 1);
        await count(page, 'text=This is the right Heading Level 2.', 1);
        await count(page, 'text=This is the left Paragraph Level 1.', 1);
        await count(page, 'text=This is the right Paragraph Level 1.', 1);
      },
    },
  ];
});
