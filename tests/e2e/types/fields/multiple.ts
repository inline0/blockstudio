import { Page, Frame } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(['fields multiple', 'fields-multiple'], '"hero_heading":"Default Heading"', () => {
  return [
    {
      description: 'check regular field and custom field defaults',
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, '"hero_heading":"Default Heading"');
        await text(canvas, '"hero_description":"Default description."');
        await text(canvas, '"hero_enabled":false');
        await text(canvas, '"card_title":"Card Title"');
      },
    },
    {
      description: 'change regular intro field',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-fields-multiple"]');
        const inputs = page.locator('.blockstudio-fields__field--text input');
        await inputs.first().fill('Welcome');
        await saveAndReload(page);
      },
    },
    {
      description: 'verify field isolation',
      testFunction: async (_page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-fields-multiple"]');
        await text(canvas, '"intro":"Welcome"');
        await text(canvas, '"hero_heading":"Default Heading"');
        await text(canvas, '"card_title":"Card Title"');
      },
    },
  ];
});
