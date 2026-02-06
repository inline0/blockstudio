import { expect, Page, Frame } from '@playwright/test';
import { testType, text } from '../../utils/playwright-utils';

testType(
  ['fields overrides', 'fields-overrides'],
  '"hero_heading":"Welcome"',
  () => {
    return [
      {
        description: 'check overridden default value',
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, '"hero_heading":"Welcome"');
        },
      },
      {
        description: 'check overridden labels',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-fields-overrides"]');
          await expect(
            page.locator('.blockstudio-fields__field--text .components-base-control__label').first()
          ).toHaveText('Title');
          await expect(
            page.locator('.blockstudio-fields__field--textarea .components-base-control__label').first()
          ).toHaveText('Subtitle');
        },
      },
      {
        description: 'check id override bypasses idStructure',
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, '"active":false');
        },
      },
    ];
  }
);
