import { expect, Page, Frame } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(['fields default', 'fields-default'], '"heading":"Default Heading"', () => {
  return [
    {
      description: 'check default values',
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, '"heading":"Default Heading"');
        await text(canvas, '"description":"Default description."');
        await text(canvas, '"enabled":false');
      },
    },
    {
      description: 'change heading field',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-fields-default"]');
        const input = page.locator('.blockstudio-fields__field--text input').first();
        await input.fill('Updated Heading');
        await saveAndReload(page);
      },
    },
    {
      description: 'verify updated heading',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-fields-default"]');
        const input = page.locator('.blockstudio-fields__field--text input').first();
        await expect(input).toHaveValue('Updated Heading');
      },
    },
  ];
});
