import { expect, Page } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(['fields default', 'fields-default'], '"heading":"Default Heading"', () => {
  return [
    {
      description: 'check default values',
      testFunction: async (page: Page) => {
        await text(page, '"heading":"Default Heading"');
        await text(page, '"description":"Default description."');
        await text(page, '"enabled":false');
      },
    },
    {
      description: 'change heading field',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-fields-default"]');
        const input = page.locator('.blockstudio-fields__field--text input').first();
        await input.fill('Updated Heading');
        await saveAndReload(page);
      },
    },
    {
      description: 'verify updated heading',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-fields-default"]');
        const input = page.locator('.blockstudio-fields__field--text input').first();
        await expect(input).toHaveValue('Updated Heading');
      },
    },
  ];
});
