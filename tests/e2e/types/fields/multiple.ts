import { expect, Page } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(['fields multiple', 'fields-multiple'], '"hero_heading":"Default Heading"', () => {
  return [
    {
      description: 'check regular field and custom field defaults',
      testFunction: async (page: Page) => {
        await text(page, '"hero_heading":"Default Heading"');
        await text(page, '"hero_description":"Default description."');
        await text(page, '"hero_enabled":false');
        await text(page, '"card_title":"Card Title"');
      },
    },
    {
      description: 'change regular intro field',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-fields-multiple"]');
        const inputs = page.locator('.blockstudio-fields__field--text input');
        await inputs.first().fill('Welcome');
        await saveAndReload(page);
      },
    },
    {
      description: 'verify field isolation',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-fields-multiple"]');
        await text(page, '"intro":"Welcome"');
        await text(page, '"hero_heading":"Default Heading"');
        await text(page, '"card_title":"Card Title"');
      },
    },
  ];
});
