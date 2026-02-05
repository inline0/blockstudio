import { expect, Page } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(
  ['fields id-structure', 'fields-id-structure'],
  '"hero_heading":"Default Heading"',
  () => {
    return [
      {
        description: 'check prefixed attribute IDs',
        testFunction: async (page: Page) => {
          await text(page, '"hero_heading":"Default Heading"');
          await text(page, '"hero_description":"Default description."');
          await text(page, '"hero_enabled":false');
        },
      },
      {
        description: 'change prefixed field value',
        testFunction: async (page: Page) => {
          await page.click('[data-type="blockstudio/type-fields-id-structure"]');
          const input = page.locator('.blockstudio-fields__field--text input').first();
          await input.fill('Prefixed Heading');
          await saveAndReload(page);
        },
      },
      {
        description: 'verify prefixed field persists',
        testFunction: async (page: Page) => {
          await page.click('[data-type="blockstudio/type-fields-id-structure"]');
          await text(page, '"hero_heading":"Prefixed Heading"');
        },
      },
    ];
  }
);
