import { Page } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(['fields filter', 'fields-filter'], '"text":"Click Me"', () => {
  return [
    {
      description: 'check PHP filter registered field defaults',
      testFunction: async (page: Page) => {
        await text(page, '"text":"Click Me"');
      },
    },
    {
      description: 'change filter field value',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-fields-filter"]');
        const input = page.locator('.blockstudio-fields__field--text input').first();
        await input.fill('Sign Up');
        await saveAndReload(page);
      },
    },
    {
      description: 'verify filter field persists',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-fields-filter"]');
        await text(page, '"text":"Sign Up"');
      },
    },
  ];
});
