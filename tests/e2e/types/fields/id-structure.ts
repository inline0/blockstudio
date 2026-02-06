import { Page, Frame } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(
  ['fields id-structure', 'fields-id-structure'],
  '"hero_heading":"Default Heading"',
  () => {
    return [
      {
        description: 'check prefixed attribute IDs',
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, '"hero_heading":"Default Heading"');
          await text(canvas, '"hero_description":"Default description."');
          await text(canvas, '"hero_enabled":false');
        },
      },
      {
        description: 'change prefixed field value',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-fields-id-structure"]');
          const input = page.locator('.blockstudio-fields__field--text input').first();
          await input.fill('Prefixed Heading');
          await saveAndReload(page);
        },
      },
      {
        description: 'verify prefixed field persists',
        testFunction: async (_page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-fields-id-structure"]');
          await text(canvas, '"hero_heading":"Prefixed Heading"');
        },
      },
    ];
  }
);
